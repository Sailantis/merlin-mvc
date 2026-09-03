<?php

namespace Azera\Orm;

use Azera\Db\Database;

/**
 * Flush scheduler for entities tracked in the {@see Heap}.
 *
 * flush() = diff -> commands -> topological order (owners before dependents,
 * so auto-generated owner PKs backfill into dependents' FK values) ->
 * execute -> backfill IDs -> mark MANAGED, inside Database::begin/commit
 * (nested transactions already supported by Database).
 *
 * Fast path: a trivial single-entity flush adds only UoW overhead - no
 * command objects, no tx when one is already open (joins the caller's tx).
 *
 * v1 speaks SQL directly through Database (Store seam + MongoStore come in
 * Phase 6); write-strategy selection (RETURNING matrix) lives here.
 */
final class UnitOfWork
{
    public function __construct(
        private Heap $heap,
        private Database $db,
    )
    {
    }

    /**
     * Queue an entity for INSERT (or UPDATE if already MANAGED).
     */
    public function persist(object $entity): void
    {
        $node = $this->heap->find($entity);

        if ($node === null) {
            $this->scheduleInsert($entity);
        } elseif ($node->state === Node::MANAGED) {
            $this->scheduleUpdate($entity);
        }
        // SCHEDULED_* states: already queued, nothing to do.
    }

    /**
     * Queue an entity for DELETE.
     */
    public function remove(object $entity): void
    {
        $node = $this->heap->find($entity);

        if ($node === null || $node->state === Node::SCHEDULED_INSERT) {
            // Never persisted (or pending insert cancelled): nothing in
            // storage to remove; drop from identity tracking.
            $this->heap->detach($entity);
            return;
        }

        $node->state = Node::SCHEDULED_DELETE;
    }

    /**
     * Flush all scheduled work in one transaction.
     */
    public function flush(): void
    {
        $scheduled = $this->heap->scheduled();
        if ($scheduled === []) {
            return; // fast path: nothing to do
        }

        $startedTx = false;
        if (!$this->inTransaction()) {
            $this->db->begin();
            $startedTx = true;
        }

        try {
            foreach ($this->order($scheduled) as $node) {
                $this->execute($node);
            }

            if ($startedTx) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTx) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    private function inTransaction(): bool
    {
        return $this->db->inTransaction();
    }

    /* ------------------------------------------------------ scheduling */

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
            return; // clean: UoW does NOT write unchanged entities
        }

        $node->data          = array_merge($node->data, $diff);
        $node->changedFields = array_keys($diff);
        $node->state         = Node::SCHEDULED_UPDATE;
    }

    /* --------------------------------------------------------- diffing */

    /**
     * Entity vs node snapshot diff, restricted to metadata columns.
     * Scalar-only comparison (the heap stores scalar row values).
     */
    private function diff(object $entity, Node $node, array $meta): array
    {
        $current = $this->extractData($entity, $meta);
        $changed = [];

        foreach ($current as $field => $value) {
            $orig = $node->data[$field] ?? null;
            if ($value !== $orig) {
                $changed[$field] = $value;
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

    /* ---------------------------------------------- data extraction */

    /**
     * Entity -> raw store row keyed by COLUMN NAME (store representation).
     * DateTime objects formatted; null stays null; scalars pass through.
     */
    private function extractData(object $entity, array $meta): array
    {
        $data = [];
        foreach ($meta['columns'] as $field => $col) {
            $value = $entity->{$field} ?? null;

            if (\is_object($value) && $value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $data[$col['name']] = $value;
        }

        return $data;
    }

    /* ------------------------------------------------- write strategies */

    /**
    * Per-situation RETURNING strategy (user requirement: NOT always
    * RETURNING *). Decided from metadata + row content at flush time:
    *
    * - pk_set:      caller set the PK (sentinel-ID pattern) -> plain INSERT,
    *                nothing to backfill (fastest).
    * - returning_id: PK is auto-increment AND only the PK is missing from
    *                the row (all other columns were set) -> INSERT...
    *                RETURNING only the PK column (small payload).
    - last_insert_id: driver has no RETURNING (or RETURNING would return
    *                everything anyway) -> plain INSERT + lastInsertId().
    * - returning_all: driver-supported RETURNING when defaults/triggers
    *                may fill columns the caller did not set.
    */
    private function returningStrategy(Node $node, array $meta, array $data): string
    {
        $pkCols = array_values(array_filter($meta['columns'], fn($c) => $c['pk']));
        if ($pkCols === []) {
            return 'last_insert_id'; // no PK: nothing to backfill
        }

        // Caller set the PK (sentinel pattern): plain INSERT.
        $idFields = $node->id;
        // pk_set check: all PK values non-null in the incoming data?
        $pkSet = true;
        foreach ($pkCols as $col) {
            if (($data[$col['name']] ?? null) === null) {
                $pkSet = false;
                break;
            }
        }

        if ($pkSet) {
            return 'pk_set';
        }

        // Auto-increment path: RETURNING if driver supports it, else
        // lastInsertId (MySQL).
        if (!$this->db->supportsReturning()) {
            return 'last_insert_id';
        }

        // Columns other than the PK that the caller did NOT set: defaults /
        // triggers may fill them -> RETURNING *; otherwise RETURNING id is
        // the smallest payload.
        $nonPkNames = array_map(
            fn($c) => $c['name'],
            array_filter($meta['columns'], fn($c) => !$c['pk'])
        );
        $missing = array_diff($nonPkNames, array_keys($data));

        return $missing === [] ? 'returning_id' : 'returning_all';
    }

    private function executeInsert(Node $node): void
    {
        $meta   = Metadata::for($node->class);
        $entity = $this->entityFor($node);

        // Columns the caller actually set (null = not set â†’ omitted from
        // INSERT so DB defaults apply).
        $data = $this->extractData($entity, $meta);
        $set  = array_filter($data, fn($v) => $v !== null);

        $strategy = $this->returningStrategy($node, $meta, $set);
        $db       = $this->db;

        switch ($strategy) {
            case 'pk_set':
                $db->query(
                    $this->sqlInsert($meta, $set),
                    $this->params($set)
                );
                break;

            case 'returning_id':
                $pkCol  = $this->pkColumn($meta);
                $pkName = $pkCol['name'];
                $sql    = $this->sqlInsert($meta, $set)
                    . ' RETURNING ' . $db->quoteIdentifier($pkName);
                $row = $db->selectRow($sql, $this->params($set), \PDO::FETCH_ASSOC);
                if (isset($row[$pkName])) {
                    $this->backfill($node, $entity, [$pkName => $row[$pkName]]);
                }
                break;

            case 'last_insert_id':
                $db->query($this->sqlInsert($meta, $set), $this->params($set));
                $pkCol = $this->pkColumn($meta);
                $id    = $db->lastInsertId();
                if ($id !== false && $id !== '' && $id !== '0') {
                    $this->backfill($node, $entity, [$pkCol['name'] => (int) $id]);
                }
                break;

            case 'returning_all':
                $sql = $this->sqlInsert($meta, $set) . ' RETURNING *';
                $row = $db->selectRow($sql, $this->params($set), \PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    $this->applyRow($node, $entity, $row);
                }
                break;
        }

        $node->state = Node::MANAGED;
    }

    /**
     * UPDATE ... WHERE pk = identity. Writes only changed columns.
     */
    private function executeUpdate(Node $node): void
    {
        $meta   = Metadata::for($node->class);
        $entity = $this->entityFor($node);

        // scheduleUpdate() merged the diff into node->data and set
        // changedFields â€” the UPDATE writes exactly those columns.
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

        $this->db->query(
            $this->sqlUpdate($meta, $changed, $idWhere),
            [...$this->params($changed), ...$this->params($idWhere)]
        );

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

        $this->db->query(
            $this->sqlDelete($meta, $idWhere),
            $this->params($idWhere)
        );

        $node->state = Node::DELETED;

        $entity = $this->entityFor($node);
        if ($entity !== null) {
            $this->heap->detach($entity);
        }
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
        }

        foreach ($row as $colName => $value) {
            $field = $byCol[$colName] ?? null;
            if ($field !== null) {
                $entity->{$field} = $value;
            }
        }

        $this->backfill($node, $entity, $row);
    }

    /* ------------------------------------------------------ SQL shapes */

    /**
     * INSERT INTO (cols) VALUES (?, ...) â€” shape cached per class+set keys.
     */
    private function sqlInsert(array $meta, array $set): string
    {
        $cols = array_map(
            fn($c) => $this->db->quoteIdentifier($c),
            array_keys($set)
        );

        return 'INSERT INTO '
            . $this->quotedSource($meta)
            . ' (' . implode(', ', $cols) . ') VALUES ('
            . implode(', ', array_fill(0, \count($cols), '?'))
            . ')';
    }

    /**
     * UPDATE SET col = ? ... WHERE pk = ? AND pk2 = ?
     */
    private function sqlUpdate(array $meta, array $changed, array $idWhere): string
    {
        $sets = [];
        foreach (array_keys($changed) as $col) {
            $sets[] = $this->db->quoteIdentifier($col) . ' = ?';
        }

        $wheres = [];
        foreach (array_keys($idWhere) as $col) {
            $wheres[] = $this->db->quoteIdentifier($col) . ' = ?';
        }

        return 'UPDATE ' . $this->quotedSource($meta)
            . ' SET ' . implode(', ', $sets)
            . ' WHERE ' . implode(' AND ', $wheres);
    }

    /**
     * DELETE FROM ... WHERE pk = ? AND pk2 = ?
     */
    private function sqlDelete(array $meta, array $idWhere): string
    {
        $wheres = [];
        foreach (array_keys($idWhere) as $col) {
            $wheres[] = $this->db->quoteIdentifier($col) . ' = ?';
        }

        return 'DELETE FROM ' . $this->quotedSource($meta)
            . ' WHERE ' . implode(' AND ', $wheres);
    }

    private function quotedSource(array $meta): string
    {
        // metadata source is the static fallback; dynamic tenancy overrides
        // still come from source()/schema() on the instance (Phase 2 note).
        return $this->db->quoteIdentifier($meta['source']);
    }

    /**
     * Positional params in the order the SQL references them.
     */
    private function params(array $data): array
    {
        return array_values($data);
    }
}
