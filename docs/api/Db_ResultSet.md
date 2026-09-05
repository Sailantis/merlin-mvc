# 🧩 Class: ResultSet

**Full name:** [Azera\Db\ResultSet](../../src/Db/ResultSet.php)

Forward-only cursor over an executed statement. Provides various fetch methods to retrieve rows as associative arrays, objects, or single column values.

## 🚀 Public methods

### __construct() · [source](../../src/Db/ResultSet.php#L38)

`public function __construct(Azera\Db\Database $connection, PDOStatement $statement, string|null $sqlStatement = null, array|null $boundParams = null, bool $isReadQuery = true): mixed`

Create a new ResultSet wrapping a PDO statement result.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$connection` | [Database](Db_Database.md) | - | SQL connection used to execute the query. |
| `$statement` | PDOStatement | - | The executed PDO statement. |
| `$sqlStatement` | string\|null | `null` | The original SQL string (used by reexecute()). |
| `$boundParams` | array\|null | `null` | Bound parameters (used by reexecute()). |
| `$isReadQuery` | bool | `true` | Whether the statement is a read-only SELECT. Defaults to true.<br>Set to false for write statements (e.g. INSERT/UPDATE/DELETE ... RETURNING),<br>which cannot be safely re-executed via `refresh()`. |

**➡️ Return value**

- Type: mixed


---

### fetch() · [source](../../src/Db/ResultSet.php#L58)

`public function fetch(): object|array|false`

Fetch next row as object or array depending on fetch mode.

**➡️ Return value**

- Type: object|array|false
- Description: The next row as an object or array depending on the fetch mode, or false if there are no more rows.


---

### fetchAssoc() · [source](../../src/Db/ResultSet.php#L68)

`public function fetchAssoc(): array|false`

Fetch next row as associative array.

**➡️ Return value**

- Type: array|false
- Description: The next row as an associative array, or false if there are no more rows.


---

### fetchObject() · [source](../../src/Db/ResultSet.php#L78)

`public function fetchObject(): object|false`

Fetch next row as object.

**➡️ Return value**

- Type: object|false
- Description: The next row as an object, or false if there are no more rows.


---

### fetchColumn() · [source](../../src/Db/ResultSet.php#L89)

`public function fetchColumn(int $column = 0): mixed`

Fetch next row as a single column value.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$column` | int | `0` | Zero-based column index to fetch, or 0 for the first column. |

**➡️ Return value**

- Type: mixed
- Description: The value of the specified column in the next row, or false if there are no more rows.


---

### fetchAllAssoc() · [source](../../src/Db/ResultSet.php#L99)

`public function fetchAllAssoc(): array`

Return all rows as associative array.

**➡️ Return value**

- Type: array
- Description: An array of all remaining rows, each as an associative array.


---

### fetchAllObject() · [source](../../src/Db/ResultSet.php#L110)

`public function fetchAllObject(): array`

Return all rows as object.

**➡️ Return value**

- Type: array
- Description: An array of all remaining rows, each as an object.


---

### fetchAllColumn() · [source](../../src/Db/ResultSet.php#L122)

`public function fetchAllColumn(int $column = 0): array`

Fetch all values from a single column.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$column` | int | `0` | Zero-based column index to fetch, or 0 for the first column. |

**➡️ Return value**

- Type: array
- Description: The values of the specified column in all remaining rows.


---

### fetchAll() · [source](../../src/Db/ResultSet.php#L134)

`public function fetchAll(int $fetchMode = 0): array`

Fetch all rows as objects or arrays depending on fetch mode.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$fetchMode` | int | `0` | PDO::FETCH_* constant or 0 for default fetch mode |

**➡️ Return value**

- Type: array
- Description: An array of all remaining rows, each as an object or array depending on the fetch mode.


---

### setFetchMode() · [source](../../src/Db/ResultSet.php#L145)

`public function setFetchMode(int $fetchMode): void`

Set the default fetch mode for this result set.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$fetchMode` | int | - | One of the PDO::FETCH_* constants |

**➡️ Return value**

- Type: void


---

### getSql() · [source](../../src/Db/ResultSet.php#L154)

`public function getSql(): string|null`

Return the SQL statement that was executed to produce this result set, if available.

**➡️ Return value**

- Type: string|null
- Description: The SQL statement string, or null if not available.


---

### getBindings() · [source](../../src/Db/ResultSet.php#L163)

`public function getBindings(): array|null`

Return the variables that were bound to the SQL statement, if available.

**➡️ Return value**

- Type: array|null
- Description: The variables that were bound to the SQL statement, or null if not available.


---

### toArray() · [source](../../src/Db/ResultSet.php#L176)

`public function toArray(): array`

Convert the result set to a plain array of rows.

Makes the result set compatible with template engines and serializers
that expect array-like data (e.g. Clarity's castToArray).

**➡️ Return value**

- Type: array
- Description: All remaining rows as arrays.


---

### refresh() · [source](../../src/Db/ResultSet.php#L191)

`public function refresh(): void`

Execute the query again to repopulate the result set.

Only read-only (SELECT) result sets can be safely refreshed. Refreshing a
write statement (e.g. INSERT/UPDATE/DELETE ... RETURNING) would re-execute
the write, so it is rejected.

**➡️ Return value**

- Type: void

**⚠️ Throws**

- Exception  If this result set does not originate from a SELECT statement.


---

### rewind() · [source](../../src/Db/ResultSet.php#L217)

`public function rewind(): void`

Rewind the iterator to the first row.

The underlying PDOStatement cursor is forward-only, so we cannot
actually rewind once iteration has started.  However, on the first
call (before any rows have been fetched) we lazily fetch the first
row so that valid() returns true and PHP's foreach can begin.

**➡️ Return value**

- Type: void


---

### current() · [source](../../src/Db/ResultSet.php#L226)

`public function current(): mixed`

Return the current row (fetched lazily on first access).

**➡️ Return value**

- Type: mixed


---

### key() · [source](../../src/Db/ResultSet.php#L236)

`public function key(): int`

Return the zero-based position of the current row within this traversal.

**➡️ Return value**

- Type: int


---

### next() · [source](../../src/Db/ResultSet.php#L242)

`public function next(): void`

Advance to the next row.

**➡️ Return value**

- Type: void


---

### valid() · [source](../../src/Db/ResultSet.php#L249)

`public function valid(): bool`

Return true while the current row is not false/null (i.e., while rows remain).

**➡️ Return value**

- Type: bool


---

### count() · [source](../../src/Db/ResultSet.php#L258)

`public function count(): int`

Return the number of rows affected/returned by the underlying statement.

**➡️ Return value**

- Type: int
- Description: Row count as reported by PDOStatement::rowCount().


---

### closeCursor() · [source](../../src/Db/ResultSet.php#L274)

`public function closeCursor(): void`

Close the cursor on the underlying PDO statement.

This releases any locks the statement may still hold.  It is especially
important for statements that only partially consumed their result set
(e.g. [`Model::__performWrite()`](Orm_Model.md#__performwrite) fetching a single RETURNING row): on
SQLite in WAL mode, an open cursor on a write statement keeps the write
lock held on its connection, blocking writes from other connections.

**➡️ Return value**

- Type: void


---

### __destruct() · [source](../../src/Db/ResultSet.php#L283)

`public function __destruct(): mixed`

Ensure the underlying statement cursor is released when the result set
goes out of scope, so that any Database locks it holds are freed.

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
