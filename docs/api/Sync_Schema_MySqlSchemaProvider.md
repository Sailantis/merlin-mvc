# 🧩 Class: MySqlSchemaProvider

**Full name:** [Azera\Sync\Schema\MySqlSchemaProvider](../../src/Sync/Schema/MySqlSchemaProvider.php)

## 🚀 Public methods

### __construct() · [source](../../src/Sync/Schema/MySqlSchemaProvider.php#L8)

`public function __construct(PDO $pdo): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$pdo` | PDO | - |  |

**➡️ Return value**

- Type: mixed


---

### listTables() · [source](../../src/Sync/Schema/MySqlSchemaProvider.php#L10)

`public function listTables(string|null $schema = null): array`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$schema` | string\|null | `null` |  |

**➡️ Return value**

- Type: array


---

### getTableSchema() · [source](../../src/Sync/Schema/MySqlSchemaProvider.php#L21)

`public function getTableSchema(string $table, string|null $schema = null): Azera\Sync\Schema\TableSchema`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$table` | string | - |  |
| `$schema` | string\|null | `null` |  |

**➡️ Return value**

- Type: [TableSchema](Sync_Schema_TableSchema.md)



---

[Back to the Index ⤴](README.md)
