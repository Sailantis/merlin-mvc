# 🧩 Class: PostgresSchemaProvider

**Full name:** [Azera\Sync\Schema\PostgresSchemaProvider](../../src/Sync/Schema/PostgresSchemaProvider.php)

## 🚀 Public methods

### __construct() · [source](../../src/Sync/Schema/PostgresSchemaProvider.php#L8)

`public function __construct(PDO $pdo): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$pdo` | PDO | - |  |

**➡️ Return value**

- Type: mixed


---

### listTables() · [source](../../src/Sync/Schema/PostgresSchemaProvider.php#L16)

`public function listTables(string|null $schema = null): array`

Lists tables, views, materialized views, and foreign tables.

PostgreSQL system schemas (pg_catalog, pg_toast, information_schema, …)
are always excluded so that model tooling only sees user tables.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$schema` | string\|null | `null` |  |

**➡️ Return value**

- Type: array


---

### getTableSchema() · [source](../../src/Sync/Schema/PostgresSchemaProvider.php#L58)

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
