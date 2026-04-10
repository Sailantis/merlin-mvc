# 🧩 Class: Sql

**Full name:** [Merlin\Db\Sql](../../src/Db/Sql.php)

SQL Value Object - Tagged Union for SQL Expressions

Represents SQL expressions (functions, casts, arrays, etc.) that serialize at SQL generation time.
Default behavior: serialize to literals (debug-friendly)
Sql::param() creates a named binding reference (:name) for use with Query::bind()

**💡 Example**

```php
// Function with literals
Sql::func('concat', ['prefix_', 'value'])
// → concat('prefix_', 'value')

// Function with named binding reference (value supplied via Query::bind())
Sql::func('concat', ['prefix_', Sql::param('id')])
// → concat('prefix_', :id)

// PostgreSQL array
Sql::pgArray(['php', 'pgsql'])
// → '{"php","pgsql"}'

// Cast (driver-specific)
Sql::cast(Sql::column('text_search'), 'tsvector')
// PostgreSQL: text_search::tsvector
// MySQL: CAST(text_search AS tsvector)
```

## 🚀 Public methods

### column() · [source](../../src/Db/Sql.php#L79)

`public static function column(string $name): static`

Column reference (unquoted identifier)
Supports Model.column syntax for automatic table resolution

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Column name (simple or Model.column format) |

**➡️ Return value**

- Type: static


---

### param() · [source](../../src/Db/Sql.php#L95)

`public static function param(string $name): static`

Named binding reference — emits :name in the SQL, resolved against
the manual bindings supplied via Query::bind().

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Parameter name (must match a key in bind()) |

**➡️ Return value**

- Type: static


---

### bind() · [source](../../src/Db/Sql.php#L109)

`public static function bind(string $name, mixed $value): static`

Bound parameter — emits :name in the SQL and propagates the value as a
real PDO named parameter (not inlined as an escaped literal).

The value is merged into Query::$subQueryBindings and reaches
Database::query() via PDO execute().

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Parameter name |
| `$value` | mixed | - | Parameter value |

**➡️ Return value**

- Type: static


---

### usesPdoBinding() · [source](../../src/Db/Sql.php#L121)

`public function usesPdoBinding(): bool`

Whether this node's bind parameters should be passed as real PDO named
parameters rather than inlined as escaped literals.

**➡️ Return value**

- Type: bool


---

### func() · [source](../../src/Db/Sql.php#L132)

`public static function func(string $name, array $args = []): static`

SQL function call

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Function name |
| `$args` | array | `[]` | Function arguments (scalars or Sql instances) |

**➡️ Return value**

- Type: static


---

### cast() · [source](../../src/Db/Sql.php#L143)

`public static function cast(mixed $value, string $type): static`

Type cast (driver-specific syntax)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$value` | mixed | - | Value to cast (scalar or Sql) |
| `$type` | string | - | Target type name |

**➡️ Return value**

- Type: static


---

### pgArray() · [source](../../src/Db/Sql.php#L153)

`public static function pgArray(array $values): static`

PostgreSQL array literal

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$values` | array | - | Array elements (scalars or Sql instances) |

**➡️ Return value**

- Type: static


---

### csList() · [source](../../src/Db/Sql.php#L163)

`public static function csList(array $values): static`

Comma-separated list (for IN clauses)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$values` | array | - | List elements (scalars or Sql instances) |

**➡️ Return value**

- Type: static


---

### raw() · [source](../../src/Db/Sql.php#L174)

`public static function raw(string $sql, array $inlineValues = []): static`

Raw SQL (unescaped, passed through as-is)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$sql` | string | - | Raw SQL string |
| `$inlineValues` | array | `[]` | Optional values to be replaced in the SQL (e.g. for :name placeholders), treated as literal values (escaped) |

**➡️ Return value**

- Type: static


---

### value() · [source](../../src/Db/Sql.php#L186)

`public static function value(mixed $value): static`

Literal value (will be properly quoted/escaped)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$value` | mixed | - | Value to serialize as SQL literal |

**➡️ Return value**

- Type: static


---

### json() · [source](../../src/Db/Sql.php#L196)

`public static function json(mixed $value): static`

JSON value (serialized as JSON literal)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$value` | mixed | - | Value to encode as JSON |

**➡️ Return value**

- Type: static


---

### concat() · [source](../../src/Db/Sql.php#L208)

`public static function concat(mixed ...$parts): static`

Driver-aware string concatenation
PostgreSQL/SQLite: uses || operator
MySQL: uses CONCAT() function

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$parts` | mixed | - | Parts to concatenate (scalars or Sql instances) |

**➡️ Return value**

- Type: static


---

### expr() · [source](../../src/Db/Sql.php#L220)

`public static function expr(mixed ...$parts): static`

Composite expression - concatenates parts with spaces
Useful for complex expressions like CASE WHEN
Plain strings are treated as raw SQL tokens (not serialized)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$parts` | mixed | - | Expression parts (strings are raw, use Sql instances for values) |

**➡️ Return value**

- Type: static


---

### case() · [source](../../src/Db/Sql.php#L229)

`public static function case(): Merlin\Db\SqlCase`

CASE expression builder

**➡️ Return value**

- Type: [SqlCase](Db_SqlCase.md)
- Description: Fluent builder for CASE expressions


---

### subQuery() · [source](../../src/Db/Sql.php#L239)

`public static function subQuery(Merlin\Db\Query $query): static`

Subquery expression - wraps a Query instance as a subquery

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$query` | [Query](Db_Query.md) | - | Subquery instance |

**➡️ Return value**

- Type: static


---

### as() · [source](../../src/Db/Sql.php#L249)

`public function as(string $alias): static`

Add alias to this expression (returns aliased node)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$alias` | string | - | Column alias |

**➡️ Return value**

- Type: static


---

### getBindParams() · [source](../../src/Db/Sql.php#L259)

`public function getBindParams(): array`

Get bind parameters associated with this node

**➡️ Return value**

- Type: array
- Description: Associative array of bind parameters


---

### toSql() · [source](../../src/Db/Sql.php#L319)

`public function toSql(string $driver, callable $serialize, callable|null $protectIdentifier = null): string`

Serialize node to SQL string

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$driver` | string | - | Database driver (mysql, pgsql, sqlite) |
| `$serialize` | callable | - | Callback for serializing scalar values<br>Signature: fn(mixed $value, bool $param = false): string |
| `$protectIdentifier` | callable\|null | `null` | Callback for identifier resolution and quoting<br>Signature: fn(string $identifier, ?string $alias = null, int $mode = 0): string<br>If not provided, falls back to simple driver-based quoting |

**➡️ Return value**

- Type: string
- Description: SQL fragment


---

### __toString() · [source](../../src/Db/Sql.php#L486)

`public function __toString(): string`

**➡️ Return value**

- Type: string



---

[Back to the Index ⤴](README.md)
