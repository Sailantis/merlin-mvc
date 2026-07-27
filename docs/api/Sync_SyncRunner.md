# 🧩 Class: SyncRunner

**Full name:** [Azera\Sync\SyncRunner](../../src/Sync/SyncRunner.php)

## 🚀 Public methods

### __construct() · [source](../../src/Sync/SyncRunner.php#L15)

`public function __construct(Azera\Db\DatabaseManager $dbManager): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$dbManager` | [DatabaseManager](Db_DatabaseManager.md) | - |  |

**➡️ Return value**

- Type: mixed


---

### syncModel() · [source](../../src/Sync/SyncRunner.php#L34)

`public function syncModel(string $filePath, bool $dryRun = false, string $dbRole = 'read', Azera\Sync\SyncOptions|null $options = null): Azera\Sync\SyncResult`

Synchronise a single model file against the database schema.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$filePath` | string | - | Absolute path to the model PHP file |
| `$dryRun` | bool | `false` | When true the file is NOT written; changes are only calculated |
| `$dbRole` | string | `'read'` | Database role to introspect (falls back to default if not registered) |
| `$options` | [SyncOptions](Sync_SyncOptions.md)\|null | `null` |  |

**➡️ Return value**

- Type: [SyncResult](Sync_SyncResult.md)


---

### syncAll() · [source](../../src/Sync/SyncRunner.php#L100)

`public function syncAll(array $modelFiles, bool $dryRun = false, string $dbRole = 'read', Azera\Sync\SyncOptions|null $options = null): array`

Synchronise multiple model files.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$modelFiles` | array | - | Absolute paths to model PHP files |
| `$dryRun` | bool | `false` |  |
| `$dbRole` | string | `'read'` |  |
| `$options` | [SyncOptions](Sync_SyncOptions.md)\|null | `null` |  |

**➡️ Return value**

- Type: array


---

### listDatabaseTables() · [source](../../src/Sync/SyncRunner.php#L119)

`public function listDatabaseTables(string $dbRole = 'read', string|null $schema = null): array`

Return all table names in the database for the given role and optional schema.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$dbRole` | string | `'read'` |  |
| `$schema` | string\|null | `null` | DB schema to scan (PostgreSQL only; pass null to use server default). |

**➡️ Return value**

- Type: array


---

### createModelFile() · [source](../../src/Sync/SyncRunner.php#L132)

`public function createModelFile(string $filePath, string $namespace, string $className, string $tableName, string|null $schema = null): void`

Scaffold a new model file. Throws if the file already exists.

The generated class includes an explicit source() override so the
table name is always unambiguous to subsequent sync operations.
If $schema is given, a schema() override is also generated.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$filePath` | string | - |  |
| `$namespace` | string | - |  |
| `$className` | string | - |  |
| `$tableName` | string | - |  |
| `$schema` | string\|null | `null` |  |

**➡️ Return value**

- Type: void


---

### getModelTableName() · [source](../../src/Sync/SyncRunner.php#L174)

`public function getModelTableName(string $filePath): string|null`

Resolve the table name for a model file without calculating a full diff.

Returns null if the file cannot be parsed or the class is not a valid Model.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$filePath` | string | - |  |

**➡️ Return value**

- Type: string|null


---

### getModelInfo() · [source](../../src/Sync/SyncRunner.php#L192)

`public function getModelInfo(string $filePath): array|null`

Resolve both the table name and optional DB schema for a model file.

Returns null if the file cannot be parsed or the class is not a valid Model.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$filePath` | string | - |  |

**➡️ Return value**

- Type: array|null
- Description: [$tableName, $schema]



---

[Back to the Index ⤴](README.md)
