# 🧩 Class: MongoStore

**Full name:** [Azera\Orm\Storage\MongoStore](../../src/Orm/Storage/MongoStore.php)

MongoDB backend of the [`Store`](Orm_Storage_Store.md) seam over the mongodb/mongodb library.

The stack is two layers, NOT two alternatives (unlike redis's phpredis vs
predis): ext-mongodb (PECL) is THE driver — MongoDB\Driver\Manager, BSON
encoding, wire protocol — and mongodb/mongodb (composer) is the pure-PHP
convenience API on top of it (`Client`, `MongoCollection`). The
library cannot run without the extension; using this store = using both.

OWNS its connection (the inverse of PdoStore's borrow model): mongo has no
role-based read/write split in the DatabaseManager, so the store wraps one
`Client` and resolves the per-class collection from metadata
(`collection` ?? snake/plural convention). A class belongs to exactly one
collection, declared by #[Document(collection)].

Constructor accepts EITHER a Client (production) OR a collection resolver
`fn(string $name): MongoCollection` — the test seam: an in-memory fake
collection keeps the suite hermetic (no live server, no flaky CI).

Rows are plain assoc arrays keyed by the metadata COLUMN names — identical
shape contract to PdoStore — so EntityManager's write pipeline, heap diff,
and FastHydrator work unchanged.

Identity: mongo's `_id` is THE PK — single, always present (driver-generated
ObjectId on insert when omitted). The metadata pk convention (*_id marks)
already resolves `$_id` for documents, and insert backfill maps the
inserted id onto it.

Transactions: no-ops. Multi-document ACID needs replica-set sessions —
deliberately deferred (documented); the Store seam's begin/commit/rollback
is satisfied structurally so the EM pipeline works against single-server
deployments.

## 🚀 Public methods

### __construct() · [source](../../src/Orm/Storage/MongoStore.php#L59)

`public function __construct(MongoDB\Client|callable $clientOrResolver, string $database = 'azera'): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$clientOrResolver` | MongoDB\Client\|callable | - |  |
| `$database` | string | `'azera'` |  |

**➡️ Return value**

- Type: mixed


---

### insertOne() · [source](../../src/Orm/Storage/MongoStore.php#L72)

`public function insertOne(string $class, array $data): array`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$data` | array | - |  |

**➡️ Return value**

- Type: array


---

### updateOne() · [source](../../src/Orm/Storage/MongoStore.php#L93)

`public function updateOne(string $class, array $data, array $id): array`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$data` | array | - |  |
| `$id` | array | - |  |

**➡️ Return value**

- Type: array


---

### deleteOne() · [source](../../src/Orm/Storage/MongoStore.php#L106)

`public function deleteOne(string $class, array $id): void`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$id` | array | - |  |

**➡️ Return value**

- Type: void


---

### findBy() · [source](../../src/Orm/Storage/MongoStore.php#L112)

`public function findBy(string $class, array $where): array`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$where` | array | - |  |

**➡️ Return value**

- Type: array


---

### findByPk() · [source](../../src/Orm/Storage/MongoStore.php#L120)

`public function findByPk(string $class, array $id): array|null`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$id` | array | - |  |

**➡️ Return value**

- Type: array|null


---

### count() · [source](../../src/Orm/Storage/MongoStore.php#L128)

`public function count(string $class, array $where = []): int`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$where` | array | `[]` |  |

**➡️ Return value**

- Type: int


---

### begin() · [source](../../src/Orm/Storage/MongoStore.php#L140)

`public function begin(): void`

No-ops: multi-document ACID needs replica-set sessions (deferred).

Kept structural so the EM pipeline never branches on store type.

**➡️ Return value**

- Type: void


---

### commit() · [source](../../src/Orm/Storage/MongoStore.php#L142)

`public function commit(): void`

**➡️ Return value**

- Type: void


---

### rollback() · [source](../../src/Orm/Storage/MongoStore.php#L144)

`public function rollback(): void`

**➡️ Return value**

- Type: void


---

### inTransaction() · [source](../../src/Orm/Storage/MongoStore.php#L146)

`public function inTransaction(): bool`

**➡️ Return value**

- Type: bool



---

[Back to the Index ⤴](README.md)
