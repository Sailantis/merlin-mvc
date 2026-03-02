# 🧩 Class: ResultSet

**Full name:** [Merlin\Db\ResultSet](../../src/Db/ResultSet.php)

## 🚀 Public methods

### __construct() · [source](../../src/Db/ResultSet.php#L37)

`public function __construct(Merlin\Db\Database $connection, PDOStatement $statement, string|null $sqlStatement = null, array|null $boundParams = null, Merlin\Mvc\Model|null $model = null): mixed`

Create a new ResultSet wrapping a PDO statement result.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$connection` | [Database](Db_Database.md) | - | Database connection used to execute the query. |
| `$statement` | PDOStatement | - | The executed PDO statement. |
| `$sqlStatement` | string\|null | `null` | The original SQL string (used by reexecute()). |
| `$boundParams` | array\|null | `null` | Bound parameters (used by reexecute()). |
| `$model` | [Model](Mvc_Model.md)\|null | `null` | Optional model instance used for hydration (sets the fetch class). |

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

### fetchArray() · [source](../../src/Db/ResultSet.php#L68)

`public function fetchArray(): array|false`

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

### fetchAllArray() · [source](../../src/Db/ResultSet.php#L99)

`public function fetchAllArray(): array`

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

### nextModel() · [source](../../src/Db/ResultSet.php#L154)

`public function nextModel(): Merlin\Mvc\Model|null`

Get the next model from the result set, or false if there are no more models. This method will attempt to hydrate a model if a model class was provided when the ResultSet was created. If no model class was provided, it will return false.

**➡️ Return value**

- Type: [Model](Mvc_Model.md)|null
- Description: The next model instance, or null if there are no more models.


---

### firstModel() · [source](../../src/Db/ResultSet.php#L191)

`public function firstModel(): Merlin\Mvc\Model|null`

Get first model or object from result set.

**➡️ Return value**

- Type: [Model](Mvc_Model.md)|null
- Description: The first model instance, or null if there are no models or if the first row cannot be hydrated as a model.


---

### allModels() · [source](../../src/Db/ResultSet.php#L221)

`public function allModels(): array`

Get all remaining rows hydrated as model instances.

Calls `nextModel()` repeatedly until the result set is exhausted.
Returns an empty array when no model class was provided at construction.

**➡️ Return value**

- Type: array
- Description: An array of all remaining model instances, or an empty array if there are no more models.


---

### getSql() · [source](../../src/Db/ResultSet.php#L239)

`public function getSql(): string|null`

Return the SQL statement that was executed to produce this result set, if available.

**➡️ Return value**

- Type: string|null
- Description: The SQL statement string, or null if not available.


---

### getBindings() · [source](../../src/Db/ResultSet.php#L248)

`public function getBindings(): array|null`

Return the variables that were bound to the SQL statement, if available.

**➡️ Return value**

- Type: array|null
- Description: The variables that were bound to the SQL statement, or null if not available.


---

### reexecute() · [source](../../src/Db/ResultSet.php#L257)

`public function reexecute(): void`

Execute the query again to repopulate the result set.

**➡️ Return value**

- Type: void


---

### rewind() · [source](../../src/Db/ResultSet.php#L273)

`public function rewind(): void`

Rewind is a no-op: the result set cursor is forward-only.

**➡️ Return value**

- Type: void


---

### current() · [source](../../src/Db/ResultSet.php#L279)

`public function current(): mixed`

Return the current row (fetched lazily on first access).

**➡️ Return value**

- Type: mixed


---

### key() · [source](../../src/Db/ResultSet.php#L289)

`public function key(): int`

Return the zero-based position of the current row within this traversal.

**➡️ Return value**

- Type: int


---

### next() · [source](../../src/Db/ResultSet.php#L295)

`public function next(): void`

Advance to the next row.

**➡️ Return value**

- Type: void


---

### valid() · [source](../../src/Db/ResultSet.php#L302)

`public function valid(): bool`

Return true while the current row is not false/null (i.e., while rows remain).

**➡️ Return value**

- Type: bool


---

### count() · [source](../../src/Db/ResultSet.php#L311)

`public function count(): int`

Return the number of rows affected/returned by the underlying statement.

**➡️ Return value**

- Type: int
- Description: Row count as reported by PDOStatement::rowCount().



---

[Back to the Index ⤴](index.md)
