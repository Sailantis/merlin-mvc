# 🧩 Class: ModelMapping

**Full name:** [Azera\Core\ModelMapping](../../src/Core/ModelMapping.php)

ModelMapping is a configuration class that maps logical model names to
database table sources.

It provides methods to define, retrieve, and manipulate model mappings,
including automatic conversion of model names to table names (snake_case)
and optional pluralization.

## 🚀 Public methods

### fromArray() · [source](../../src/Core/ModelMapping.php#L24)

`public static function fromArray(array $mapping): static`

Create ModelMapping from array config

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$mapping` | array | - |  |

**➡️ Return value**

- Type: static


---

### usePluralTableNames() · [source](../../src/Core/ModelMapping.php#L91)

`public static function usePluralTableNames(bool $enable): void`

Enable or disable automatic table name pluralization.

When enabled, model names are converted to plural snake_case table names
(e.g. User → users, AdminUser → admin_users, Person → people).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$enable` | bool | - |  |

**➡️ Return value**

- Type: void


---

### usingPluralTableNames() · [source](../../src/Core/ModelMapping.php#L99)

`public static function usingPluralTableNames(): bool`

Returns whether automatic table name pluralization is enabled.

**➡️ Return value**

- Type: bool


---

### add() · [source](../../src/Core/ModelMapping.php#L114)

`public function add(string $name, string|null $source = null, string|null $schema = null, string|null $connection = null, string|null $read = null, string|null $write = null): static`

Add model mapping

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |
| `$source` | string\|null | `null` |  |
| `$schema` | string\|null | `null` |  |
| `$connection` | string\|null | `null` | Connection role for both read and write |
| `$read` | string\|null | `null` | Connection role for read queries (overrides $connection) |
| `$write` | string\|null | `null` | Connection role for write queries (overrides $connection) |

**➡️ Return value**

- Type: static


---

### get() · [source](../../src/Core/ModelMapping.php#L137)

`public function get(string $name): array|null`

Get model mapping by name

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |

**➡️ Return value**

- Type: array|null


---

### toArray() · [source](../../src/Core/ModelMapping.php#L146)

`public function toArray(): array`

Get all model mappings as an array

**➡️ Return value**

- Type: array


---

### convertModelToSource() · [source](../../src/Core/ModelMapping.php#L159)

`public static function convertModelToSource(string $modelName): string`

Convert a model name to a default source name (table name).

By default, converts PascalCase or camelCase to snake_case (e.g. AdminUser → admin_user).
When pluralization is enabled, the last word segment is pluralized (e.g. AdminUser → admin_users).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$modelName` | string | - | The model class name to convert. |

**➡️ Return value**

- Type: string
- Description: The converted source name (table name).


---

### toSnakeCase() · [source](../../src/Core/ModelMapping.php#L189)

`public static function toSnakeCase(string $name): string`

Convert a string to snake_case.

Handles various input formats, including camelCase, PascalCase, kebab-case, and space-separated words.
Consecutive uppercase letters are treated as acronyms (e.g., XMLParser → xml_parser).
Multiple separators are unified into a single underscore, and duplicate underscores are avoided.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | The input string to convert. |

**➡️ Return value**

- Type: string
- Description: The converted snake_case string.



---

[Back to the Index ⤴](README.md)
