# 🧩 Class: SyncResult

**Full name:** [Merlin\Sync\SyncResult](../../src/Sync/SyncResult.php)

Holds the result of synchronising a single model file against the database schema.

## 🌍 Public Properties

- `public` string `$filePath` · [source](../../src/Sync/SyncResult.php)
- `public` string `$className` · [source](../../src/Sync/SyncResult.php)
- `public` string `$tableName` · [source](../../src/Sync/SyncResult.php)
- `public` array `$operations` · [source](../../src/Sync/SyncResult.php)
- `public` bool `$applied` · [source](../../src/Sync/SyncResult.php)
- `public` string|null `$error` · [source](../../src/Sync/SyncResult.php)

## 🚀 Public methods

### __construct() · [source](../../src/Sync/SyncResult.php#L17)

`public function __construct(string $filePath, string $className, string $tableName, array $operations, bool $applied, string|null $error = null): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$filePath` | string | - | Absolute path to the model file |
| `$className` | string | - | Fully-qualified class name |
| `$tableName` | string | - | Database table that was introspected |
| `$operations` | array | - | All diff operations calculated |
| `$applied` | bool | - | Whether the operations were written to disk |
| `$error` | string\|null | `null` | Error message, or null on success |

**➡️ Return value**

- Type: mixed


---

### hasChanges() · [source](../../src/Sync/SyncResult.php#L27)

`public function hasChanges(): bool`

**➡️ Return value**

- Type: bool


---

### isSuccess() · [source](../../src/Sync/SyncResult.php#L32)

`public function isSuccess(): bool`

**➡️ Return value**

- Type: bool


---

### addedProperties() · [source](../../src/Sync/SyncResult.php#L38)

`public function addedProperties(): array`

**➡️ Return value**

- Type: array


---

### removedProperties() · [source](../../src/Sync/SyncResult.php#L44)

`public function removedProperties(): array`

**➡️ Return value**

- Type: array


---

### typeChanges() · [source](../../src/Sync/SyncResult.php#L50)

`public function typeChanges(): array`

**➡️ Return value**

- Type: array


---

### addedAccessors() · [source](../../src/Sync/SyncResult.php#L56)

`public function addedAccessors(): array`

**➡️ Return value**

- Type: array


---

### summary() · [source](../../src/Sync/SyncResult.php#L64)

`public function summary(): string`

Human-readable summary line.

**➡️ Return value**

- Type: string



---

[Back to the Index ⤴](README.md)
