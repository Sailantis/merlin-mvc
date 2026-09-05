<?php

namespace Azera\Orm;

use Azera\AppContext;
use Azera\Lifecycle\RequestScoped;
use Azera\Orm\Cast\Casts;
use Azera\Orm\FastHydrator;
use Azera\Orm\Metadata;
use Azera\Orm\Storage\PdoStore;
use Azera\Orm\Storage\Store;
use Azera\Orm\Storage\StoreManager;

/**
 * The entity manager: identity map + write pipeline, consolidated.
 *
 * One instance per request (registered request-scoped in AppContext).
 * The Active-Record facades ({@see \Azera\Orm\Model},
 * {@see \Azera\Orm\Document}) delegate here, so facade-style and
 * EM-direct use share ONE write pipeline: diff -> topological order ->
 * transaction -> ID backfill.
 *
 * Reads probe the identity map first — find() hit = the same instance,
 * miss = one Store read + FastHydrator onto the shared heap. Works for
 * SQL models (PdoStore) and Mongo documents (once MongoStore lands).
 *
 * The write pipeline used to be a separate UnitOfWork class; it is merged
 * here because it had exactly one caller and no public surface beyond
 * persist/remove/flush. flush() = diff -> commands -> topological order
 * (owners before dependents, so auto-generated owner PKs backfill into
 * dependents' FK values) -> execute -> backfill IDs -> mark MANAGED,
 * inside the Store's transaction (nested transactions already supported
 * by Database). Fast path: a trivial single-entity flush adds only
 * scheduling overhead, and no tx when one is already open (joins the
 * caller's tx).
 *
 * Storage-agnostic: writes execute through the {@see Store} seam resolved
 * from class metadata (store: 'sql' | 'mongo'). The SQL shapes and the
 * RETURNING matrix (pk_set / returning_id / returning_all / last_insert_id)
 * live in each Store backend; flush consumes their normalized
 * ['row' => ?array, 'id' => ?scalar] results for identity backfill.
 *
 * RequestScoped: {@see resetState()} wipes the heap and drops scheduled
 * writes between requests in persistent workers (non-negotiable — same
 * contract as Heap).
 */
final class EntityManager implements RequestScoped
{
    public function __construct(
        private Heap $heap,
        private ?object $db = null,
    ) {}

    /* ------------------------------------------------------------ accessors */

    /**
     * The shared identity map.
     */
    public function heap(): Heap
    {
        return $this->heap;
    }

    /* ---------------------------------------------------------------- reads */

    /**
     * Load one entity by PK values: heap probe first, Store read on miss,
     * hydration onto the shared heap.
     *
     * @param class-string         $class
     * @param array<string, mixed> $id PK field => value
     */
    public function find(string $class, array $id): ?object
    {
        // Identity hit: the exact same object this request already loaded.
        $node = $this->heap->findById($class, $id);
        if ($node !== null) {
            return $this->heap->entityFor($node);
        }

        // Miss: one SELECT via the Store seam, hydrate into the heap.
        $row = $this->storeFor($class)->findByPk($class, $id);
        if ($row === null) {
            return null;
        }

        [$entity] = FastHydrator::for($class)->hydrate($this->heap, $row);

        return $entity;
    }

    /**
     * Load all entities matching field => value conditions.
     *
     * @param class-string         $class
     * @param array<string, mixed> $where
     * @return list<object>
     */
    public function findBy(string $class, array $where): array
    {
        $rows = $this->storeFor($class)->findBy($class, $where);

        return $this->hydrateRows($class, $rows);
    }

    /* --------------------------------------------------------------- writes */

    /**
     * Queue an entity for INSERT (or UPDATE when already managed).
     * Explicit intent — flush() sees ONLY what was persisted here
     * (the deliberate no-implicit-dirty-checking doctrine contrast).
     */
    public function persist(object $entity): static
    {
        $node = $this->heap->find($entity);

        if ($node === null) {
            $this->scheduleInsert($entity);
        } elseif ($node->state === Node::MANAGED) {
            $this->scheduleUpdate($entity);
        }
        // SCHEDULED_* states: already queued, nothing to do.

        return $this;
    }

    /**
     * Queue an entity for DELETE. Never-persisted entities (or cancelled
     * pending inserts) are just dropped from identity tracking.
     */
    public function remove(object $entity): static
    {
        $node = $this->heap->find($entity);

        if ($node === null || $node->state === Node::SCHEDULED_INSERT) {
            // Never persisted (or pending insert cancelled): nothing in
            // storage to remove; drop from identity tracking.
            $this->heap->detach($entity);
            return $this;
        }

        $node->state = Node::SCHEDULED_DELETE;

        return $this;
    }

    /**
     * Execute all scheduled writes in one transaction
     * (diff -> order -> execute -> backfill).
     *
     * Transaction control follows the SCHEDULED WORK's store types (not a
     * classless resolution): SQL stores get begin/commit/rollback; mongo
     * stores are structural no-ops (replica-set sessions deferred). A mixed
     * SQL+mongo flush wraps only the SQL side — no cross-type tx exists.
     */
    public function flush(): void
    {
        $scheduled = $this->heap->scheduled();
        if ($scheduled === []) {
            return; // fast path: nothing to do
        }

        // Resolve every scheduled class's store UP FRONT — a missing or
        // misconfigured store must fail the flush before ANY write runs,
        // not mid-flush after earlier nodes already executed. The SQL
        // store doubles as the tx target; mongo stores need none (the
        // seam's begin/commit/rollback are structural no-ops there —
        // multi-doc ACID is deferred until replica-set sessions), so a
        // mixed SQL+mongo flush wraps only the SQL side.
        $sqlStore = null;
        foreach ($scheduled as $node) {
            $store = $this->storeFor($node->class);
            if ((Metadata::for($node->class)['store'] ?? 'sql') === 'sql') {
                $sqlStore ??= $store;
            }
        }

        $startedTx = false;
        if ($sqlStore !== null && !$sqlStore->inTransaction()) {
            $sqlStore->begin();
            $startedTx = true;
        }

        try {
            foreach ($this->order($scheduled) as $node) {
                $this->execute($node);
            }

            if ($startedTx) {
                $sqlStore->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTx) {
                $sqlStore->rollback();
            }
            throw $e;
        }
    }

    /* ------------------------------------------------------------ lifecycle */

    /**
     * Drop an entity from identity tracking (no storage effect).
     */
    public function detach(object $entity): void
    {
        $this->heap->detach($entity);
    }

    /* ------------------------------------------------------------- adopt */

    /**
     * Facade adoption: register an EXTERNALLY-loaded entity as MANAGED
     * with an EMPTY baseline.
     *
     * Reads that bypass the EM pipeline (FETCH_CLASS ResultSet, Paginator)
     * produce instances that are not in the heap. The facade calls adopt()
     * before persisting. Empty baseline means the next flush writes every
     * set non-PK column — the legacy blind-UPDATE parity for manually
     * built ID'd entities (and the correct semantic: the EM cannot know
     * what the DB already holds for an entity it never loaded).
     *
     * Entities loaded through EM reads (find/findBy/entities) do NOT need
     * adopt() — their heap node carries the store snapshot from hydration.
     * The returned entity is the ADOPTED instance (heap re-attach replaces
     * the node when the entity already sits under another identity).
     */
    public function adopt(object $entity): object
    {
        $meta = Metadata::for($entity::class);

        $data = $this->extractData($entity, $meta);

        $id = [];
        foreach ($meta['columns'] as $field => $col) {
            if ($col['pk']) {
                $id[$field] = $data[$col['name']] ?? null;
            }
        }

        // Empty baseline: data=[] — diff sees every set column as changed.
        $node = new Node($meta['class'], $id, [], Node::MANAGED);
        $this->heap->attach($entity, $node);

        return $entity;
    }

    /**
     * Register an externally-loaded entity as MANAGED with its CURRENT
     * values as the baseline (the "already in sync" adoption for entities
     * loaded by reads the EM does not hydrate — FETCH_CLASS ResultSet,
     * Paginator). Unlike adopt(), persist() on a tracked() entity emits
     * SQL only for fields changed after the track() call.
     */
    public function track(object $entity): object
    {
        $meta = Metadata::for($entity::class);

        $data = $this->extractData($entity, $meta);

        $id = [];
        foreach ($meta['columns'] as $field => $col) {
            if ($col['pk']) {
                $id[$field] = $data[$col['name']] ?? null;
            }
        }

        $node = new Node($meta['class'], $id, $data, Node::MANAGED);
        $this->heap->attach($entity, $node);

        return $entity;
    }

    /**
     * Whether the entity is tracked in the request heap.
     */
    public function contains(object $entity): bool
    {
        return $this->heap->find($entity) !== null;
    }

    /**
     * Whether the entity has scheduled work in the current flush cycle.
     */
    public function isScheduled(object $entity): bool
    {
        $node = $this->heap->find($entity);

        return $node !== null && $node->isScheduled();
    }

    /* --------------------------------------------------- dirty-state API */

    /**
     * Dirty state backed by the heap node snapshot (the ONE diff engine —
     * Stateful's clone snapshot is gone).
     *
     * Untracked entity: every metadata column with a set value counts as
     * changed (the same "everything set is pending" semantic the old
     * no-snapshot Stateful path had). Tracked entity: current values vs
     * the node snapshot, field-name-keyed.
     *
     * @return array<string, mixed> field name => current value
     */
    public function dirtyData(object $entity): array
    {
        $meta = Metadata::for($entity::class);
        $node = $this->heap->find($entity);

        $byCol = [];
        foreach ($meta['columns'] as $field => $col) {
            $byCol[$col['name']] = $field;
        }

        if ($node === null) {
            $data = $this->extractData($entity, $meta);
            // Mirror Stateful's no-snapshot semantic: all set fields changed.
            return array_filter($data, fn($v) => $v !== null);
        }

        $current = $this->extractData($entity, $meta);
        $changed = [];
        foreach ($current as $col => $value) {
            if ($value !== ($node->data[$col] ?? null)) {
                $changed[$byCol[$col] ?? $col] = $value;
            }
        }

        return $changed;
    }

    /**
     * Whether the entity differs from its heap baseline (untracked entity:
     * true — it has pending state that adopt+flush would write).
     */
    public function isDirty(object $entity): bool
    {
        return $this->dirtyData($entity) !== [];
    }

    /**
     * Revert the entity's properties to the values recorded in its heap
     * node snapshot (the loadState() replacement). No-op for untracked
     * entities — nothing to revert to.
     */
    public function revert(object $entity): void
    {
        $node = $this->heap->find($entity);
        if ($node === null) {
            return;
        }

        $meta  = Metadata::for($entity::class);
        $byCol = [];
        foreach ($meta['columns'] as $field => $col) {
            $byCol[$col['name']] = $field;
        }

        foreach ($node->data as $colName => $value) {
            $field = $byCol[$colName] ?? null;
            if ($field !== null) {
                // node->data holds the raw store representation — decode
                // casted columns before assigning onto the entity.
                $cast = Casts::for($meta['columns'][$field]['type']);
                $entity->{$field} = $cast === null ? $value : $cast->decode($value);
            }
        }
    }

    /**
     * Wipe ALL tracked state (identity + scheduled writes). Scheduled
     * work is dropped, NOT flushed — explicit clear means "forget".
     */
    public function clear(): void
    {
        $this->heap->resetState();
    }

    /**
     * Request-scoped hook: wipe the identity map + any scheduled writes
     * between requests in persistent workers.
     */
    public function resetState(): void
    {
        $this->heap->resetState();
    }

    /* ------------------------------------------------------- scheduling */

    /**
     * NEW node: capture PK values (may be empty for auto-increment),
     * record raw data, attach to heap, schedule INSERT.
     */
    private function scheduleInsert(object $entity): void
    {
        $meta = Metadata::for($entity::class);
        $data = $this->extractData($entity, $meta);

        $id = [];
        foreach ($meta['columns'] as $field => $col) {
            if ($col['pk']) {
                $id[$field] = $data[$field] ?? null;
            }
        }

        $node = new Node($entity::class, $id, $data, Node::SCHEDULED_INSERT);
        $this->heap->attach($entity, $node);
    }

    /**
     * MANAGED entity: diff against the node snapshot; schedule UPDATE if dirty.
     */
    private function scheduleUpdate(object $entity): void
    {
        $node = $this->heap->find($entity);
        if ($node === null) {
            return;
        }

        $meta = Metadata::for($entity::class);
        $diff = $this->diff($entity, $node, $meta);
        if ($diff === []) {
            return; // clean: the pipeline does NOT write unchanged entities
        }

        $node->data          = array_merge($node->data, $diff);
        $node->changedFields = array_keys($diff);
        $node->state         = Node::SCHEDULED_UPDATE;
    }

    /* --------------------------------------------------------- diffing */

    /**
     * Entity vs node snapshot diff, restricted to metadata columns.
     * Scalar-only comparison (the heap stores scalar row values).
     * PK columns are identity — never part of an UPDATE SET (changing a
     * PK is delete+insert semantics, and legacy saves never wrote them).
     */
    private function diff(object $entity, Node $node, array $meta): array
    {
        $current = $this->extractData($entity, $meta);
        $changed = [];

        $pkCols = [];
        foreach ($meta['columns'] as $col) {
            if ($col['pk']) {
                $pkCols[$col['name']] = true;
            }
        }

        foreach ($current as $col => $value) {
            if (isset($pkCols[$col])) {
                continue;
            }
            $orig = $node->data[$col] ?? null;
            if ($value !== $orig) {
                $changed[$col] = $value;
            }
        }

        return $changed;
    }

    /* -------------------------------------------------------- ordering */

    /**
     * Topological order: entities whose class is the TARGET of another
     * scheduled entity's BelongsTo (owners) flush first, so auto-generated
     * owner PKs backfill into dependents' FKs. Everything else keeps
     * attach order. Depth computed over metadata relations; O(n) classes.
     */
    private function order(array $nodes): array
    {
        if (\count($nodes) < 2) {
            return $nodes;
        }

        // Class-level depth: owner classes (targets of belongsTo) get
        // depth 0, dependents 1, etc.
        $depth = [];
        foreach ($nodes as $node) {
            $depth[$node->class] = $depth[$node->class] ?? 0;
        }

        foreach ($nodes as $node) {
            foreach (Metadata::for($node->class)['relations'] as $rel) {
                if ($rel['type'] === 'belongsTo') {
                    $owner = $rel['target'];
                    $depth[$owner] = $depth[$owner] ?? 0;
                    // dependent's depth must exceed its owner's
                    $depth[$node->class] = max($depth[$node->class] ?? 0, $depth[$owner] + 1);
                }
            }
        }

        usort($nodes, fn($a, $b) => ($depth[$a->class] ?? 0) <=> ($depth[$b->class] ?? 0));

        return $nodes;
    }

    /* ------------------------------------------------------- execution */

    private function execute(Node $node): void
    {
        switch ($node->state) {
            case Node::SCHEDULED_INSERT:
                $this->executeInsert($node);
                break;
            case Node::SCHEDULED_UPDATE:
                $this->executeUpdate($node);
                break;
            case Node::SCHEDULED_DELETE:
                $this->executeDelete($node);
                break;
        }
    }

    private function executeInsert(Node $node): void
    {
        $meta   = Metadata::for($node->class);
        $entity = $this->entityFor($node);

        // Columns the caller actually set (null = not set → omitted from
        // INSERT so DB defaults apply).
        $data = $this->extractData($entity, $meta);
        $set  = array_filter($data, fn($v) => $v !== null);

        // Store executes the INSERT with its own strategy matrix and
        // returns the normalized backfill payload.
        $result = $this->storeFor($node->class)->insertOne($node->class, $set);

        // Mark MANAGED BEFORE applyRow/backfill: both re-attach a NEW node
        // to the heap carrying $node->state — setting the state after that
        // would only flip the ORPHANED old node, leaving the heap entry
        // stuck in SCHEDULED_INSERT forever (next persist → duplicate
        // INSERT; exposed by the mongo live round-trip, latent for SQL
        // id-backfill inserts as well).
        $node->state = Node::MANAGED;

        if (isset($result['row']) && is_array($result['row'])) {
            $this->applyRow($node, $entity, $result['row']);
        } elseif (($result['id'] ?? null) !== null) {
            $pk = $this->pkColumn($meta);
            $this->backfill($node, $entity, [$pk['name'] => $result['id']]);
        }
    }

    /**
     * UPDATE ... WHERE pk = identity. Writes only changed columns.
     */
    private function executeUpdate(Node $node): void
    {
        $meta = Metadata::for($node->class);

        // scheduleUpdate() set changedFields (COLUMN names) and merged the
        // diff into node->data — the UPDATE writes exactly those columns.
        $changed = array_intersect_key($node->data, array_flip($node->changedFields));
        if ($changed === []) {
            $node->state = Node::MANAGED;
            return;
        }

        // WHERE uses the identity captured at schedule time.
        $idWhere = [];
        foreach ($meta['columns'] as $field => $col) {
            if ($col['pk']) {
                $idWhere[$col['name']] = $node->id[$field] ?? null;
            }
        }

        $this->storeFor($node->class)->updateOne($node->class, $changed, $idWhere);

        $node->state = Node::MANAGED;
    }

    /**
     * DELETE ... WHERE pk = identity. Detaches the entity afterwards.
     */
    private function executeDelete(Node $node): void
    {
        $meta = Metadata::for($node->class);

        $idWhere = [];
        foreach ($meta['columns'] as $field => $col) {
            if ($col['pk']) {
                $idWhere[$col['name']] = $node->id[$field] ?? null;
            }
        }

        $this->storeFor($node->class)->deleteOne($node->class, $idWhere);

        $node->state = Node::DELETED;

        $entity = $this->entityFor($node);
        if ($entity !== null) {
            $this->heap->detach($entity);
        }
    }

    /* ---------------------------------------------- data extraction */

    /**
     * Entity -> raw store row keyed by COLUMN NAME (store representation).
     * Values with a registered cast are ENCODED here (json -> text, pg
     * array -> literal; scalar casts are encode no-ops); DateTime objects
     * formatted for SQL stores; null stays null; isset() (never a bare
     * read) so uninitialized typed properties don't throw. Mongo stores
     * bypass ALL value shaping — arrays/objects pass raw, the driver owns
     * BSON encoding (a 'json' cast is inert there by design).
     *
     * This is the single encode choke point: every write path (schedule,
     * diff, adopt, track, dirtyData) funnels through it, so node->data and
     * every Store payload hold the raw store representation and diff()'s
     * `!==` compares like with like.
     */
    private function extractData(object $entity, array $meta): array
    {
        $data = [];
        foreach ($meta['columns'] as $field => $col) {
            $value = isset($entity->{$field}) ? $entity->{$field} : null;

            if (($meta['store'] ?? 'sql') === 'mongo') {
                // Raw pass-through: the mongodb driver maps PHP arrays and
                // DateTimeInterface to BSON natively.
            } elseif (\is_object($value) && $value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (($cast = Casts::for($col['type'])) !== null) {
                $value = $cast->encode($value);
            }

            $data[$col['name']] = $value;
        }

        return $data;
    }

    /* ---------------------------------------------------------- helpers */

    /**
     * Resolve the entity object for a node via the heap's oid index.
     */
    private function entityFor(Node $node): ?object
    {
        return $this->heap->entityFor($node);
    }

    /**
     * First PK column from metadata.
     */
    private function pkColumn(array $meta): array
    {
        foreach ($meta['columns'] as $col) {
            if ($col['pk']) {
                return $col;
            }
        }

        throw new \RuntimeException("No PK column in metadata for {$meta['class']}");
    }

    /**
     * Write identity values back onto the entity + node.
     */
    private function backfill(Node $node, object $entity, array $values): void
    {
        $meta = Metadata::for($node->class);

        foreach ($values as $colName => $value) {
            foreach ($meta['columns'] as $field => $col) {
                if ($col['name'] === $colName) {
                    $entity->{$field} = $value;
                    $node->data[$colName] = $value;
                }
            }
        }

        // Refresh node identity.
        $id = [];
        foreach ($meta['columns'] as $field => $col) {
            if ($col['pk']) {
                $id[$field] = $entity->{$field} ?? null;
            }
        }

        $this->heap->attach($entity, new Node(
            $node->class,
            $id,
            array_merge($node->data, $values),
            $node->state,
        ));
    }

    /**
     * Apply a full RETURNING * row onto the entity + node.
     */
    private function applyRow(Node $node, object $entity, array $row): void
    {
        $meta = Metadata::for($node->class);

        $byCol = [];
        foreach ($meta['columns'] as $field => $col) {
            $byCol[$col['name']] = $field;
            // Decode raw store values for casted columns before they land
            // on the entity (RETURNING * rows are store representation).
            if (($cast = Casts::for($col['type'])) !== null) {
                $cast->decode($row[$col['name']] ?? null); // warm errors early
            }
        }

        foreach ($row as $colName => $value) {
            $field = $byCol[$colName] ?? null;
            if ($field !== null) {
                $cast = Casts::for($meta['columns'][$field]['type']);
                $entity->{$field} = $cast === null ? $value : $cast->decode($value);
            }
        }

        $this->backfill($node, $entity, $row);
    }

    /* ---------------------------------------------------------- reads */

    /**
     * Hydrate raw rows onto the shared heap, in row order.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<object>
     */
    private function hydrateRows(string $class, array $rows): array
    {
        $hydrator = FastHydrator::for($class);
        $out      = [];
        foreach ($rows as $row) {
            [$entity] = $hydrator->hydrate($this->heap, $row);
            if ($entity !== null) {
                $out[] = $entity;
            }
        }
        return $out;
    }

    /* ----------------------------------------------------- store seam */

    /**
     * Resolve the Store for a class, keyed by metadata `store` type:
     * StoreManager::getOrDefault(storeType, storeRole). The type comes from
     * metadata ('sql' | 'mongo') — the hard routing guarantee: #[Document]
     * classes can not fall into the SQL PdoStore regardless of what roles
     * are registered, because the two maps never mix and getOrDefault never
     * crosses types. Fallback (direct construction — tests, scripts,
     * StoreManager-less contexts): a PdoStore on the injected Database, or
     * the DatabaseManager's default connection when none was injected. Mongo
     * classes in a StoreManager-less context throw — a SQL fallback would
     * silently write a table. The db param keeps positional compatibility
     * with pre-seam callers.
     */
    private function storeFor(string $class): Store
    {
        $meta = $class !== '' ? Metadata::for($class) : null;
        $type = $meta['store'] ?? 'sql';
        $role = $meta['storeRole'] ?? 'default';

        $ctx = AppContext::instance();
        if ($ctx->has(StoreManager::class)) {
            $stores = $ctx->get(StoreManager::class);
            try {
                return $stores->getOrDefault($type, $role);
            } catch (\RuntimeException) {}
        }

        // No usable StoreManager: borrow a default connection directly.
        if (($meta['store'] ?? 'sql') === 'mongo') {
            throw new \RuntimeException(
                "Mongo document {$class} needs a StoreManager with a mongo " .
                'store registered for role ' . "'{$role}'"
            );
        }

        try {
            $db = $this->db ?? $ctx->dbManager()->getOrDefault('default');
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(
                'EntityManager has no Database and no StoreManager registered',
                0,
                $e
            );
        }
        $dbm = new \Azera\Db\DatabaseManager();
        $dbm->set('default', $db);
        $dbm->set('read', $db);
        $dbm->set('write', $db);

        return new PdoStore($dbm, 'read', 'write');
    }

    /**
     * Legacy classless resolution — now only reachable via storeFor('')
     * (metadata-less context → SQL default). Kept for any residual
     * internal callers; flush() no longer uses it.
     */
    private function store(): Store
    {
        return $this->storeFor('');
    }
}