# 🔌 Interface: Store

**Full name:** [Azera\Orm\Storage\Store](../../src/Orm/Storage/Store.php)

Persistence-level seam between the ORM and any storage backend.

Operations the EntityManager's write pipeline performs — NOT a query builder. SQL stores
implement it over a [`Database`](Db_Database.md); Mongo over the
mongodb library. The per-situation write strategies (RETURNING matrix)
live in each backend. A model belongs to exactly one store, declared in
metadata (store: 'sql' | 'mongo' + storeRole).

## 🚀 Public methods

### insertOne() · [source](../../src/Orm/Storage/Store.php#L23)

`public function insertOne(string $class, array $data): array`

Persist one entity: INSERT or UPDATE (upsert when flagged).

Returns raw row(s) for backfill: ['row' => ?array, 'id' => int|string|null].

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$data` | array | - | column-name-keyed raw values |

**➡️ Return value**

- Type: array


---

### updateOne() · [source](../../src/Orm/Storage/Store.php#L32)

`public function updateOne(string $class, array $data, array $id): array`

Update one entity by PK values.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$data` | array | - | column-name-keyed changed values |
| `$id` | array | - | PK field => value |

**➡️ Return value**

- Type: array


---

### upsertOne() · [source](../../src/Orm/Storage/Store.php#L45)

`public function upsertOne(string $class, array $data): array`

Atomic UPSERT: INSERT ... ON CONFLICT DO UPDATE (SQL) / updateOne
with upsert:true (Mongo). The caller-set PK is the conflict target —
$data must carry every PK column. Existence is resolved BY THE
DATABASE at write time: no prior SELECT, no insert-or-update guess.

Returns raw row(s) for backfill, same contract as insertOne
(RETURNING * when unset non-PK columns should refresh DB defaults).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$data` | array | - | column-name-keyed raw values (PK included) |

**➡️ Return value**

- Type: array


---

### deleteOne() · [source](../../src/Orm/Storage/Store.php#L51)

`public function deleteOne(string $class, array $id): void`

Delete one entity by PK values.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$id` | array | - | PK field => value |

**➡️ Return value**

- Type: void


---

### findBy() · [source](../../src/Orm/Storage/Store.php#L60)

`public function findBy(string $class, array $where): array`

Read raw rows. Returns plain assoc rows (no ResultSet).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$where` | array | - | PK field => value, or field                          => value |

**➡️ Return value**

- Type: array


---

### findByPk() · [source](../../src/Orm/Storage/Store.php#L68)

`public function findByPk(string $class, array $id): array|null`

Read one raw row by PK values (null when missing).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$id` | array | - | PK field => value |

**➡️ Return value**

- Type: array|null


---

### count() · [source](../../src/Orm/Storage/Store.php#L74)

`public function count(string $class, array $where = []): int`

Count matching rows.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$where` | array | `[]` | field => value |

**➡️ Return value**

- Type: int


---

### begin() · [source](../../src/Orm/Storage/Store.php#L78)

`public function begin(): void`

**➡️ Return value**

- Type: void


---

### commit() · [source](../../src/Orm/Storage/Store.php#L79)

`public function commit(): void`

**➡️ Return value**

- Type: void


---

### rollback() · [source](../../src/Orm/Storage/Store.php#L80)

`public function rollback(): void`

**➡️ Return value**

- Type: void


---

### inTransaction() · [source](../../src/Orm/Storage/Store.php#L85)

`public function inTransaction(): bool`

Whether a transaction (or savepoint level) is active.

**➡️ Return value**

- Type: bool



---

[Back to the Index ⤴](README.md)
