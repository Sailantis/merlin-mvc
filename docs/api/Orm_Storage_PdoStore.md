# 🧩 Class: PdoStore

**Full name:** [Azera\Orm\Storage\PdoStore](../../src/Orm/Storage/PdoStore.php)

SQL backend of the [`Store`](Orm_Storage_Store.md) seam over a [`Database`](Db_Database.md).

BORROWS, never owns: holds read/write ROLE STRINGS and resolves the live
connection via DatabaseManager per operation â€” the same pattern as
Model::readConnection(). Consequences: QB + legacy save() + ORM flush all
share ONE connection per role (transactions join the caller's nesting);
per-call role resolution keeps dynamic tenancy; cost = one cached array
read. ALL SQL goes through the connection so Db events fire (tracking).

Holds the RETURNING matrix: pk_set -> plain INSERT; all non-PK cols set +
driver RETURNING -> RETURNING id; unset non-PK cols -> RETURNING *;
no-RETURNING driver -> lastInsertId.

## 🚀 Public methods

### __construct() · [source](../../src/Orm/Storage/PdoStore.php#L25)

`public function __construct(Azera\Db\DatabaseManager $dbm, string $readRole = 'read', string $writeRole = 'write'): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$dbm` | [DatabaseManager](Db_DatabaseManager.md) | - |  |
| `$readRole` | string | `'read'` |  |
| `$writeRole` | string | `'write'` |  |

**➡️ Return value**

- Type: mixed


---

### insertOne() · [source](../../src/Orm/Storage/PdoStore.php#L31)

`public function insertOne(string $class, array $data): array`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$data` | array | - |  |

**➡️ Return value**

- Type: array


---

### updateOne() · [source](../../src/Orm/Storage/PdoStore.php#L66)

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

### deleteOne() · [source](../../src/Orm/Storage/PdoStore.php#L77)

`public function deleteOne(string $class, array $id): void`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$id` | array | - |  |

**➡️ Return value**

- Type: void


---

### findBy() · [source](../../src/Orm/Storage/PdoStore.php#L85)

`public function findBy(string $class, array $where): array`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$where` | array | - |  |

**➡️ Return value**

- Type: array


---

### findByPk() · [source](../../src/Orm/Storage/PdoStore.php#L94)

`public function findByPk(string $class, array $id): array|null`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$id` | array | - |  |

**➡️ Return value**

- Type: array|null


---

### count() · [source](../../src/Orm/Storage/PdoStore.php#L100)

`public function count(string $class, array $where = []): int`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$where` | array | `[]` |  |

**➡️ Return value**

- Type: int


---

### begin() · [source](../../src/Orm/Storage/PdoStore.php#L109)

`public function begin(): void`

**➡️ Return value**

- Type: void


---

### commit() · [source](../../src/Orm/Storage/PdoStore.php#L114)

`public function commit(): void`

**➡️ Return value**

- Type: void


---

### rollback() · [source](../../src/Orm/Storage/PdoStore.php#L119)

`public function rollback(): void`

**➡️ Return value**

- Type: void


---

### inTransaction() · [source](../../src/Orm/Storage/PdoStore.php#L124)

`public function inTransaction(): bool`

**➡️ Return value**

- Type: bool



---

[Back to the Index ⤴](README.md)
