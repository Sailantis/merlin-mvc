# 🧩 Class: EntityManager

**Full name:** [Azera\Orm\EntityManager](../../src/Orm/EntityManager.php)

The entity manager: identity map + write pipeline, consolidated.

One instance per request (registered request-scoped in AppContext).
The Active-Record facades ([`Model`](Orm_Model.md),
[`Document`](Orm_Document.md)) delegate here, so facade-style and
EM-direct use share ONE write pipeline: diff -> topological order ->
transaction -> ID backfill.

Reads probe the identity map first — find() hit = the same instance,
miss = one Store read + FastHydrator onto the shared heap. Works for
SQL models (PdoStore) and Mongo documents.

The write pipeline used to be a separate UnitOfWork class; it is merged
here because it had exactly one caller and no public surface beyond
persist/remove/flush. flush() = diff -> commands -> topological order
(owners before dependents, so auto-generated owner PKs backfill into
dependents' FK values) -> execute -> backfill IDs -> mark MANAGED,
inside the Store's transaction (nested transactions already supported
by Database). Fast path: a trivial single-entity flush adds only
scheduling overhead, and no tx when one is already open (joins the
caller's tx).

Storage-agnostic: writes execute through the [`Store`](Orm_Storage_Store.md) seam resolved
from class metadata (store: 'sql' | 'mongo'). The SQL shapes and the
RETURNING matrix (pk_set / returning_id / returning_all / last_insert_id)
live in each Store backend; flush consumes their normalized
['row' => ?array, 'id' => ?scalar] results for identity backfill.

RequestScoped: `resetState()` wipes the heap and drops scheduled
writes between requests in persistent workers (non-negotiable — same
contract as Heap).

## 🚀 Public methods

### __construct() · [source](../../src/Orm/EntityManager.php#L49)

`public function __construct(Azera\Orm\Heap $heap, object|null $db = null): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$heap` | [Heap](Orm_Heap.md) | - |  |
| `$db` | object\|null | `null` |  |

**➡️ Return value**

- Type: mixed


---

### heap() · [source](../../src/Orm/EntityManager.php#L59)

`public function heap(): Azera\Orm\Heap`

The shared identity map.

**➡️ Return value**

- Type: [Heap](Orm_Heap.md)


---

### find() · [source](../../src/Orm/EntityManager.php#L73)

`public function find(string $class, array $id): object|null`

Load one entity by PK values: heap probe first, Store read on miss,
hydration onto the shared heap.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$id` | array | - | PK field => value |

**➡️ Return value**

- Type: object|null


---

### findBy() · [source](../../src/Orm/EntityManager.php#L99)

`public function findBy(string $class, array $where): array`

Load all entities matching field => value conditions.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$where` | array | - |  |

**➡️ Return value**

- Type: array


---

### persist() · [source](../../src/Orm/EntityManager.php#L113)

`public function persist(object $entity): static`

Queue an entity for INSERT (or UPDATE when already managed).

Explicit intent — flush() sees ONLY what was persisted here
(the deliberate no-implicit-dirty-checking doctrine contrast).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$entity` | object | - |  |

**➡️ Return value**

- Type: static


---

### upsert() · [source](../../src/Orm/EntityManager.php#L138)

`public function upsert(object $entity): static`

Queue a single-statement UPSERT (INSERT ... ON CONFLICT DO UPDATE /
mongo updateOne upsert:true): the DATABASE resolves insert-vs-update
at write time — no prior SELECT, no insert-or-update guess, no
unique-violation race. Deliberately intent-based like persist(): the
caller asserts "row with this PK should exist afterwards", and the
store makes it so atomically.

Requires a full identity (every PK field set) — the PK is the
conflict target. Anything less is an ordinary insert.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$entity` | object | - |  |

**➡️ Return value**

- Type: static


---

### remove() · [source](../../src/Orm/EntityManager.php#L167)

`public function remove(object $entity): static`

Queue an entity for DELETE. Never-persisted entities (or cancelled
pending inserts) are just dropped from identity tracking.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$entity` | object | - |  |

**➡️ Return value**

- Type: static


---

### flush() · [source](../../src/Orm/EntityManager.php#L192)

`public function flush(): void`

Execute all scheduled writes in one transaction
(diff -> order -> execute -> backfill).

Transaction control follows the SCHEDULED WORK's store types (not a
classless resolution): SQL stores get begin/commit/rollback; mongo
stores are structural no-ops (replica-set sessions deferred). A mixed
SQL+mongo flush wraps only the SQL side — no cross-type tx exists.

**➡️ Return value**

- Type: void


---

### detach() · [source](../../src/Orm/EntityManager.php#L241)

`public function detach(object $entity): void`

Drop an entity from identity tracking (no storage effect).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$entity` | object | - |  |

**➡️ Return value**

- Type: void


---

### adopt() · [source](../../src/Orm/EntityManager.php#L264)

`public function adopt(object $entity): object`

Facade adoption: register an EXTERNALLY-loaded entity as MANAGED
with an EMPTY baseline.

Reads that bypass the EM pipeline (FETCH_CLASS ResultSet, Paginator)
produce instances that are not in the heap. The facade calls adopt()
before persisting. Empty baseline means the next flush writes every
set non-PK column — the legacy blind-UPDATE parity for manually
built ID'd entities (and the correct semantic: the EM cannot know
what the DB already holds for an entity it never loaded).

Entities loaded through EM reads (find/findBy/entities) do NOT need
adopt() — their heap node carries the store snapshot from hydration.
The returned entity is the ADOPTED instance (heap re-attach replaces
the node when the entity already sits under another identity).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$entity` | object | - |  |

**➡️ Return value**

- Type: object


---

### track() · [source](../../src/Orm/EntityManager.php#L291)

`public function track(object $entity): object`

Register an externally-loaded entity as MANAGED with its CURRENT
values as the baseline (the "already in sync" adoption for entities
loaded by reads the EM does not hydrate — FETCH_CLASS ResultSet,
Paginator). Unlike adopt(), persist() on a tracked() entity emits
SQL only for fields changed after the track() call.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$entity` | object | - |  |

**➡️ Return value**

- Type: object


---

### contains() · [source](../../src/Orm/EntityManager.php#L313)

`public function contains(object $entity): bool`

Whether the entity is tracked in the request heap.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$entity` | object | - |  |

**➡️ Return value**

- Type: bool


---

### isScheduled() · [source](../../src/Orm/EntityManager.php#L321)

`public function isScheduled(object $entity): bool`

Whether the entity has scheduled work in the current flush cycle.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$entity` | object | - |  |

**➡️ Return value**

- Type: bool


---

### dirtyData() · [source](../../src/Orm/EntityManager.php#L341)

`public function dirtyData(object $entity): array`

Dirty state backed by the heap node snapshot (the ONE diff engine —
Stateful's clone snapshot is gone).

Untracked entity: every metadata column with a set value counts as
changed (the same "everything set is pending" semantic the old
no-snapshot Stateful path had). Tracked entity: current values vs
the node snapshot, field-name-keyed.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$entity` | object | - |  |

**➡️ Return value**

- Type: array
- Description: field name => current value


---

### isDirty() · [source](../../src/Orm/EntityManager.php#L372)

`public function isDirty(object $entity): bool`

Whether the entity differs from its heap baseline (untracked entity:
true — it has pending state that adopt+flush would write).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$entity` | object | - |  |

**➡️ Return value**

- Type: bool


---

### revert() · [source](../../src/Orm/EntityManager.php#L382)

`public function revert(object $entity): void`

Revert the entity's properties to the values recorded in its heap
node snapshot (the loadState() replacement). No-op for untracked
entities — nothing to revert to.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$entity` | object | - |  |

**➡️ Return value**

- Type: void


---

### clear() · [source](../../src/Orm/EntityManager.php#L410)

`public function clear(): void`

Wipe ALL tracked state (identity + scheduled writes). Scheduled
work is dropped, NOT flushed — explicit clear means "forget".

**➡️ Return value**

- Type: void


---

### resetState() · [source](../../src/Orm/EntityManager.php#L419)

`public function resetState(): void`

Request-scoped hook: wipe the identity map + any scheduled writes
between requests in persistent workers.

**➡️ Return value**

- Type: void



---

[Back to the Index ⤴](README.md)
