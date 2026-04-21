# 🧩 Class: Query

**Full name:** [Merlin\Db\Query](../../src/Db/Query.php)

Unified query builder for SELECT, INSERT, UPDATE, DELETE operations

**💡 Example**

```php
// SELECT
$users = Query::new()->table('users')->where('active', 1)->select();
$user = Query::new()->table('users')->where('id', 5)->first();

// INSERT
Query::new()->table('users')->insert(['name' => 'John', 'email' => 'john@example.com']);

// UPSERT with ON CONFLICT/ON DUPLICATE KEY UPDATE
Query::new()->table('users')->upsert(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);

// UPDATE
Query::new()->table('users')->where('id', 5)->update(['name' => 'Jane']);

// DELETE
Query::new()->table('users')->where('id', 5)->delete();

// EXISTS / COUNT
$exists = Query::new()->table('users')->where('email', 'test@example.com')->exists();
$count = Query::new()->table('users')->where('active', 1)->count();
```

## 🚀 Public methods

### useModels() · [source](../../src/Db/Query.php#L57)

`public static function useModels(bool $useModels): void`

Enable or disable automatic model resolution for queries. If enabled, the query will resolve table names and database connections from model classes. If disabled, the query will treat table names as literal and use database connections from AppContext. This can be useful for simple queries or when you want to avoid coupling to model classes.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$useModels` | bool | - |  |

**➡️ Return value**

- Type: void


---

### setModelMapping() · [source](../../src/Db/Query.php#L66)

`public static function setModelMapping(Merlin\Mvc\ModelMapping|null $modelMapping): void`

Set the model mapping instance to use for resolving model class names to table names and database connections. This can be used instead of model classes for simple queries or when you want to avoid coupling to model classes.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$modelMapping` | [ModelMapping](Mvc_ModelMapping.md)\|null | - |  |

**➡️ Return value**

- Type: void


---

### __construct() · [source](../../src/Db/Query.php#L160)

`public function __construct(Merlin\Db\Database|null $db = null, Merlin\Mvc\Model|null $model = null): mixed`

Constructor. Can optionally pass a Database connection to use for this query, or a Model to automatically set the table and connection.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$db` | [Database](Db_Database.md)\|null | `null` |  |
| `$model` | [Model](Mvc_Model.md)\|null | `null` |  |

**➡️ Return value**

- Type: mixed


---

### new() · [source](../../src/Db/Query.php#L171)

`public static function new(Merlin\Db\Database|null $db = null): static`

Factory method to create a new Query instance. Can optionally pass a Database connection to use for this query.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$db` | [Database](Db_Database.md)\|null | `null` |  |

**➡️ Return value**

- Type: static


---

### table() · [source](../../src/Db/Query.php#L208)

`public function table(string $name, string|null $alias = null): static`

Set the table for this query. Can be either a table name or a model class name. If a model class name is provided, the corresponding table will be used and the model's database connection will be used if no connection is set on the query.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Table name or model class name |
| `$alias` | string\|null | `null` | Optional table alias |

**➡️ Return value**

- Type: static

**⚠️ Throws**

- Exception


---

### from() · [source](../../src/Db/Query.php#L227)

`public function from(Merlin\Db\Query|string $source, string|null $alias = null): static`

Set the source for this query from a subquery or raw table expression. The subquery will be wrapped in parentheses and treated as a table. An optional alias can be provided for the subquery.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$source` | [Query](Db_Query.md)\|string | - | Subquery or raw table expression |
| `$alias` | string\|null | `null` | Optional alias for the subquery |

**➡️ Return value**

- Type: static

**⚠️ Throws**

- Exception


---

### columns() · [source](../../src/Db/Query.php#L260)

`public function columns(array|string $columns): static`

Set columns for SELECT queries. Can be either a comma-separated string or an array of column names.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$columns` | array\|string | - |  |

**➡️ Return value**

- Type: static


---

### limit() · [source](../../src/Db/Query.php#L277)

`public function limit(int $limit, int|null $offset = null): static`

Set the LIMIT and optional OFFSET for SELECT queries
(or limit number of rows affected for UPDATE/DELETE)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$limit` | int | - | Number of rows to limit |
| `$offset` | int\|null | `null` | Optional offset for the limit |

**➡️ Return value**

- Type: static


---

### offset() · [source](../../src/Db/Query.php#L291)

`public function offset(int $offset): static`

Sets an OFFSET clause for SELECT queries

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$offset` | int | - | Number of rows to offset |

**➡️ Return value**

- Type: static


---

### values() · [source](../../src/Db/Query.php#L305)

`public function values(object|array $values, bool $escape = true): static`

Adds values for INSERT or UPDATE queries. Can be either:
- An associative array of column => value pairs
- An object with public properties

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$values` | object\|array | - |  |
| `$escape` | bool | `true` |  |

**➡️ Return value**

- Type: static


---

### bulkValues() · [source](../../src/Db/Query.php#L329)

`public function bulkValues(array $valuesList = [], bool $escape = true): static`

Set multiple rows of values for bulk insert operations.

Each item in the list should be an array of column => value pairs.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$valuesList` | array | `[]` |  |
| `$escape` | bool | `true` |  |

**➡️ Return value**

- Type: static


---

### hasValues() · [source](../../src/Db/Query.php#L349)

`public function hasValues(): bool`

Check if any values have been set for this query

**➡️ Return value**

- Type: bool


---

### set() · [source](../../src/Db/Query.php#L363)

`public function set(array|string $column, mixed $value = null, bool $escape = true): static`

Set a value for INSERT or UPDATE queries. Can be either:
- A single column name and value pair
- An associative array of column => value pairs

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$column` | array\|string | - |  |
| `$value` | mixed | `null` |  |
| `$escape` | bool | `true` |  |

**➡️ Return value**

- Type: static


---

### innerJoin() · [source](../../src/Db/Query.php#L393)

`public function innerJoin(Merlin\Db\Query|string $model, Merlin\Db\Condition|string|null $alias = null, Merlin\Db\Condition|string|null $conditions = null): static`

Adds an INNER join to the query

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$model` | [Query](Db_Query.md)\|string | - |  |
| `$alias` | [Condition](Db_Condition.md)\|string\|null | `null` |  |
| `$conditions` | [Condition](Db_Condition.md)\|string\|null | `null` |  |

**➡️ Return value**

- Type: static

**⚠️ Throws**

- Exception


---

### leftJoin() · [source](../../src/Db/Query.php#L406)

`public function leftJoin(Merlin\Db\Query|string $model, Merlin\Db\Condition|string|null $alias = null, Merlin\Db\Condition|string|null $conditions = null): static`

Adds a LEFT join to the query

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$model` | [Query](Db_Query.md)\|string | - |  |
| `$alias` | [Condition](Db_Condition.md)\|string\|null | `null` |  |
| `$conditions` | [Condition](Db_Condition.md)\|string\|null | `null` |  |

**➡️ Return value**

- Type: static

**⚠️ Throws**

- Exception


---

### rightJoin() · [source](../../src/Db/Query.php#L419)

`public function rightJoin(Merlin\Db\Query|string $model, Merlin\Db\Condition|string|null $alias = null, Merlin\Db\Condition|string|null $conditions = null): static`

Adds a RIGHT join to the query

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$model` | [Query](Db_Query.md)\|string | - |  |
| `$alias` | [Condition](Db_Condition.md)\|string\|null | `null` |  |
| `$conditions` | [Condition](Db_Condition.md)\|string\|null | `null` |  |

**➡️ Return value**

- Type: static

**⚠️ Throws**

- Exception


---

### crossJoin() · [source](../../src/Db/Query.php#L432)

`public function crossJoin(Merlin\Db\Query|string $model, Merlin\Db\Condition|string|null $alias = null, Merlin\Db\Condition|string|null $conditions = null): static`

Adds a CROSS join to the query

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$model` | [Query](Db_Query.md)\|string | - |  |
| `$alias` | [Condition](Db_Condition.md)\|string\|null | `null` |  |
| `$conditions` | [Condition](Db_Condition.md)\|string\|null | `null` |  |

**➡️ Return value**

- Type: static

**⚠️ Throws**

- Exception


---

### join() · [source](../../src/Db/Query.php#L446)

`public function join(Merlin\Db\Query|string $model, Merlin\Db\Condition|string|null $alias = null, Merlin\Db\Condition|string|null $conditions = null, string|null $type = null): static`

Add a JOIN clause to the query

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$model` | [Query](Db_Query.md)\|string | - |  |
| `$alias` | [Condition](Db_Condition.md)\|string\|null | `null` |  |
| `$conditions` | [Condition](Db_Condition.md)\|string\|null | `null` |  |
| `$type` | string\|null | `null` |  |

**➡️ Return value**

- Type: static

**⚠️ Throws**

- Exception


---

### orderBy() · [source](../../src/Db/Query.php#L507)

`public function orderBy(array|string $orderBy): static`

Set ORDER BY clause

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$orderBy` | array\|string | - |  |

**➡️ Return value**

- Type: static


---

### bind() · [source](../../src/Db/Query.php#L520)

`public function bind(object|array $bindParams): static`

Bind parameters for prepared statements. Can be either an associative array or an object with properties as parameter names.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$bindParams` | object\|array | - |  |

**➡️ Return value**

- Type: static


---

### returnSql() · [source](../../src/Db/Query.php#L534)

`public function returnSql(bool $returnSql = true): static`

Set whether to return the SQL string instead of executing the query

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$returnSql` | bool | `true` |  |

**➡️ Return value**

- Type: static


---

### distinct() · [source](../../src/Db/Query.php#L549)

`public function distinct(bool $distinct): static`

Set DISTINCT modifier for SELECT queries

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$distinct` | bool | - |  |

**➡️ Return value**

- Type: static


---

### injectBeforeColumns() · [source](../../src/Db/Query.php#L560)

`public function injectBeforeColumns(string $inject): static`

Set a string to be injected before the column list in SELECT queries (e.g. for SQL_CALC_FOUND_ROWS in MySQL)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$inject` | string | - |  |

**➡️ Return value**

- Type: static


---

### groupBy() · [source](../../src/Db/Query.php#L571)

`public function groupBy(array|string $groupBy): static`

Set GROUP BY clause

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$groupBy` | array\|string | - |  |

**➡️ Return value**

- Type: static


---

### forUpdate() · [source](../../src/Db/Query.php#L584)

`public function forUpdate(bool $forUpdate): static`

Sets a FOR UPDATE clause (MySQL/PostgreSQL) or FOR SHARE (PostgreSQL)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$forUpdate` | bool | - |  |

**➡️ Return value**

- Type: static


---

### sharedLock() · [source](../../src/Db/Query.php#L595)

`public function sharedLock(bool $sharedLock): static`

Sets a LOCK IN SHARE MODE / FOR SHARE clause (MySQL/PostgreSQL)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$sharedLock` | bool | - |  |

**➡️ Return value**

- Type: static


---

### replace() · [source](../../src/Db/Query.php#L610)

`public function replace(bool $replace = true): static`

Mark this as a REPLACE INTO operation (MySQL/SQLite)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$replace` | bool | `true` |  |

**➡️ Return value**

- Type: static


---

### ignore() · [source](../../src/Db/Query.php#L621)

`public function ignore(bool $ignore = true): static`

Set IGNORE modifier for INSERT (MySQL/SQLite) or ON CONFLICT DO NOTHING (PostgreSQL)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$ignore` | bool | `true` |  |

**➡️ Return value**

- Type: static


---

### updateValues() · [source](../../src/Db/Query.php#L635)

`public function updateValues(array $updateValues, bool $escape = true): static`

Set values for ON CONFLICT/ON DUPLICATE KEY UPDATE clause. Can be either:
- List array -> EXCLUDED/VALUES mode
- Assoc array -> explicit values

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$updateValues` | array | - |  |
| `$escape` | bool | `true` |  |

**➡️ Return value**

- Type: static


---

### conflict() · [source](../../src/Db/Query.php#L666)

`public function conflict(array|string $columnsOrConstraint): static`

Set conflict target for ON CONFLICT clause (PostgreSQL). Can be either:
- Array with column names
- String with column names or constraint name

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$columnsOrConstraint` | array\|string | - |  |

**➡️ Return value**

- Type: static


---

### returning() · [source](../../src/Db/Query.php#L678)

`public function returning(array|string|null $columns): static`

Set columns to return from an INSERT/UPDATE/DELETE query. Supported by PostgreSQL (RETURNING) and MySQL (RETURNING with MySQL 8.0.27+)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$columns` | array\|string\|null | - |  |

**➡️ Return value**

- Type: static

**⚠️ Throws**

- Exception


---

### toSql() · [source](../../src/Db/Query.php#L699)

`public function toSql(): string`

Compile and return the SQL string for this query without executing it

**➡️ Return value**

- Type: string

**⚠️ Throws**

- Exception


---

### select() · [source](../../src/Db/Query.php#L713)

`public function select(array|string|null $columns = null): Merlin\Db\ResultSet|string`

Execute SELECT query and return ResultSet or return SQL string if returnSql is enabled

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$columns` | array\|string\|null | `null` | Columns to select, or null to ignore parameter. Can be either a comma-separated string or an array of column names. |

**➡️ Return value**

- Type: [ResultSet](Db_ResultSet.md)|string

**⚠️ Throws**

- Exception


---

### first() · [source](../../src/Db/Query.php#L738)

`public function first(): Merlin\Mvc\Model|string|null`

Execute SELECT query and return first model or null or return SQL string if returnSql is enabled

**➡️ Return value**

- Type: [Model](Mvc_Model.md)|string|null
- Description: First model, or SQL string, or null if no results

**⚠️ Throws**

- Exception


---

### insert() · [source](../../src/Db/Query.php#L753)

`public function insert(array|null $data = null): Merlin\Db\ResultSet|array|string|bool`

Execute INSERT or UPSERT query or return SQL string if returnSql is enabled

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$data` | array\|null | `null` | Data to insert |

**➡️ Return value**

- Type: [ResultSet](Db_ResultSet.md)|array|string|bool
- Description: Insert ID, true on success, or SQL string, or result of returning clause

**⚠️ Throws**

- Exception


---

### upsert() · [source](../../src/Db/Query.php#L764)

`public function upsert(array|null $data = null): Merlin\Db\ResultSet|array|string|bool`

Execute UPSERT query (INSERT with ON CONFLICT/ON DUPLICATE KEY UPDATE) or return SQL string if returnSql is enabled

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$data` | array\|null | `null` | Data to insert |

**➡️ Return value**

- Type: [ResultSet](Db_ResultSet.md)|array|string|bool
- Description: Insert ID, true on success, or SQL string, or result of returning clause

**⚠️ Throws**

- Exception


---

### update() · [source](../../src/Db/Query.php#L803)

`public function update(array|null $data = null): Merlin\Db\ResultSet|array|string|int`

Execute UPDATE query or return SQL string if returnSql is enabled

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$data` | array\|null | `null` | Data to update |

**➡️ Return value**

- Type: [ResultSet](Db_ResultSet.md)|array|string|int
- Description: Number of affected rows or SQL string, or row of returning clause

**⚠️ Throws**

- Exception


---

### delete() · [source](../../src/Db/Query.php#L833)

`public function delete(): Merlin\Db\ResultSet|array|string|int`

Execute DELETE query

**➡️ Return value**

- Type: [ResultSet](Db_ResultSet.md)|array|string|int
- Description: Number of affected rows, SQL string, or result of returning clause

**⚠️ Throws**

- Exception


---

### truncate() · [source](../../src/Db/Query.php#L858)

`public function truncate(): string|int`

Execute TRUNCATE query or return SQL string if returnSql is enabled

**➡️ Return value**

- Type: string|int
- Description: Number of affected rows or SQL string

**⚠️ Throws**

- Exception


---

### exists() · [source](../../src/Db/Query.php#L879)

`public function exists(): string|bool`

Check if any rows exist matching the query

**➡️ Return value**

- Type: string|bool

**⚠️ Throws**

- Exception


---

### count() · [source](../../src/Db/Query.php#L906)

`public function count(): string|int`

Count rows matching the query

**➡️ Return value**

- Type: string|int
- Description: Number of matching rows or SQL string

**⚠️ Throws**

- Exception


---

### getBindings() · [source](../../src/Db/Query.php#L1616)

`public function getBindings(): array`

Get bind parameters

**➡️ Return value**

- Type: array


---

### paginate() · [source](../../src/Db/Query.php#L1628)

`public function paginate(int $page = 1, int $pageSize = 30, bool $reverse = false): Merlin\Db\Paginator`

Create a paginator for the current query

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$page` | int | `1` | Page number (1-based) |
| `$pageSize` | int | `30` | Number of items per page |
| `$reverse` | bool | `false` | Whether to reverse the order of results (for efficient deep pagination) |

**➡️ Return value**

- Type: [Paginator](Db_Paginator.md)


---

### getRowCount() · [source](../../src/Db/Query.php#L1667)

`public function getRowCount(): int`

Return the number of affected rows for write operations or the number of rows in the result set for read operations

**➡️ Return value**

- Type: int



---

[Back to the Index ⤴](README.md)
