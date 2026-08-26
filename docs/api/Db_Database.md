# 🧩 Class: Database

**Full name:** [Azera\Db\Database](../../src/Db/Database.php)

Class Database

## 🚀 Public methods

### __construct() · [source](../../src/Db/Database.php#L62)

`public function __construct(string $dsn, string $user = '', string $pass = '', array $options = []): mixed`

Create a new database connection using the provided DSN, credentials and options.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$dsn` | string | - |  |
| `$user` | string | `''` |  |
| `$pass` | string | `''` |  |
| `$options` | array | `[]` |  |

**➡️ Return value**

- Type: mixed

**⚠️ Throws**

- Exception


---

### connect() · [source](../../src/Db/Database.php#L96)

`public function connect(): mixed`

Establish a new PDO connection using the current configuration

**➡️ Return value**

- Type: mixed

**⚠️ Throws**

- Exception


---

### events() · [source](../../src/Db/Database.php#L113)

`public function events(): Psr\EventDispatcher\EventDispatcherInterface`

Resolve the event dispatcher from AppContext, cached on first call.

Returns a NullEventDispatcher when no real dispatcher is registered,
so dispatch() is always safe and cheap (single no-op method call).

**➡️ Return value**

- Type: Psr\EventDispatcher\EventDispatcherInterface


---

### setAutoReconnect() · [source](../../src/Db/Database.php#L129)

`public function setAutoReconnect(bool $enabled = true, int $maxAttempts = 0, float $retryDelay = 1, float $backoffMultiplier = 2, float $maxRetryDelay = 30, bool $jitter = true, callable|null $onReconnect = null): static`

Configure automatic reconnection behavior with detailed options

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$enabled` | bool | `true` | Enable or disable auto-reconnect |
| `$maxAttempts` | int | `0` | Maximum number of retry attempts (0 for unlimited) |
| `$retryDelay` | float | `1` | Initial delay between retries in seconds |
| `$backoffMultiplier` | float | `2` | Multiplier for exponential backoff |
| `$maxRetryDelay` | float | `30` | Maximum delay between retries in seconds |
| `$jitter` | bool | `true` | Whether to add random jitter to retry delays |
| `$onReconnect` | callable\|null | `null` | Optional callback invoked on successful reconnect (receives attempt number and db instance) |

**➡️ Return value**

- Type: static


---

### getAutoReconnect() · [source](../../src/Db/Database.php#L154)

`public function getAutoReconnect(): array|bool`

Get auto-reconnect configuration

**➡️ Return value**

- Type: array|bool


---

### query() · [source](../../src/Db/Database.php#L166)

`public function query(string $statement, array|null $params = null): PDOStatement|bool`

Execute a SQL statement with optional parameters and return the resulting statement or success status.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$statement` | string | - | SQL statement to execute |
| `$params` | array\|null | `null` | Optional parameters for prepared statements |

**➡️ Return value**

- Type: PDOStatement|bool

**⚠️ Throws**

- Exception


---

### prepare() · [source](../../src/Db/Database.php#L209)

`public function prepare(string $statement): Azera\Db\Statement`

Prepare a SQL statement and return a Statement wrapper.

Each call returns an independent Statement that owns its PDO
statement, so any number of statements can be prepared and executed
concurrently without clobbering a single shared slot.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$statement` | string | - | SQL statement to prepare |

**➡️ Return value**

- Type: [Statement](Db_Statement.md)

**⚠️ Throws**

- Exception


---

### processPdoException() · [source](../../src/Db/Database.php#L236)

`public function processPdoException(PDOException $exception, string $operation, string|null $sql = null, array|null $params = null): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$exception` | PDOException | - |  |
| `$operation` | string | - | The database operation that failed. |
| `$sql` | string\|null | `null` | The SQL statement that failed, if known. |
| `$params` | array\|null | `null` | Bound parameters for the failed statement, if known. |

**➡️ Return value**

- Type: mixed

**⚠️ Throws**

- Exception


---

### selectRow() · [source](../../src/Db/Database.php#L367)

`public function selectRow(string $query, array|null $params = null, int $fetchMode = 0): array|bool`

Fetch a single row from the database as object, associative array, or numeric array depending on the specified fetch mode.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$query` | string | - |  |
| `$params` | array\|null | `null` |  |
| `$fetchMode` | int | `0` |  |

**➡️ Return value**

- Type: array|bool


---

### selectAll() · [source](../../src/Db/Database.php#L382)

`public function selectAll(string $query, array|null $params = null, int $fetchMode = 0): array`

Fetch all rows from the database as an array of objects, associative arrays, or numeric arrays depending on the specified fetch mode.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$query` | string | - |  |
| `$params` | array\|null | `null` |  |
| `$fetchMode` | int | `0` |  |

**➡️ Return value**

- Type: array


---

### rowCount() · [source](../../src/Db/Database.php#L394)

`public function rowCount(): int`

Return the number of rows affected by the last executed statement.

**➡️ Return value**

- Type: int
- Description: Number of affected rows, or 0 if no statement has been executed.


---

### lastInsertId() · [source](../../src/Db/Database.php#L406)

`public function lastInsertId(string|null $table = null, string|null $field = null): string|bool`

Get the ID generated by the last INSERT statement.

For PostgreSQL, pass the table and primary key field to use currval(pg_get_serial_sequence()).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$table` | string\|null | `null` | Table name (PostgreSQL only). |
| `$field` | string\|null | `null` | Primary key field name (PostgreSQL only). |

**➡️ Return value**

- Type: string|bool
- Description: The last insert ID as a string, or false on failure.


---

### begin() · [source](../../src/Db/Database.php#L442)

`public function begin(bool $nesting = true): int|bool`

Begin a new transaction, or create a savepoint if nested transactions are enabled and a transaction is already active.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$nesting` | bool | `true` | Whether to use savepoints for nested transactions (if supported by the driver). |

**➡️ Return value**

- Type: int|bool
- Description: True or the number of affected rows on success.

**⚠️ Throws**

- RuntimeException  If the transaction cannot be started.


---

### commit() · [source](../../src/Db/Database.php#L486)

`public function commit(bool $nesting = true): int|bool`

Commit the current transaction or release the current savepoint (for nested transactions).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$nesting` | bool | `true` | Whether to use savepoints for nested transactions (if supported by the driver). |

**➡️ Return value**

- Type: int|bool
- Description: True or the number of affected rows on success.

**⚠️ Throws**

- RuntimeException  If there is no active transaction.


---

### rollback() · [source](../../src/Db/Database.php#L533)

`public function rollback(bool $nesting = true): int|bool`

Rollback the current transaction or to a savepoint if nesting is enabled and supported by the driver.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$nesting` | bool | `true` | Whether to use savepoints for nested transactions (if supported by the driver) |

**➡️ Return value**

- Type: int|bool

**⚠️ Throws**

- Exception


---

### quote() · [source](../../src/Db/Database.php#L579)

`public function quote(string|null $str): string|bool`

Quote a string for use in a query.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$str` | string\|null | - |  |

**➡️ Return value**

- Type: string|bool


---

### quoteIdentifier() · [source](../../src/Db/Database.php#L594)

`public function quoteIdentifier(string|null ...$args): string`

Quote one or more identifier parts (schema, table, column) using the driver-appropriate quote character.

Parts are joined with a dot separator. NULL parts are skipped. "*" is passed through unquoted.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$args` | string\|null | - | Identifier parts to quote and join (e.g. schema, table, column). |

**➡️ Return value**

- Type: string
- Description: Fully quoted identifier string.


---

### getInternalConnection() · [source](../../src/Db/Database.php#L623)

`public function getInternalConnection(): PDO|null`

Return the underlying PDO connection instance.

**➡️ Return value**

- Type: PDO|null
- Description: The PDO instance, or null if not connected.


---

### builder() · [source](../../src/Db/Database.php#L632)

`public function builder(): Azera\Db\Query`

Create a new Query builder instance associated with this database connection.

**➡️ Return value**

- Type: [Query](Db_Query.md)


---

### supportsReturning() · [source](../../src/Db/Database.php#L645)

`public function supportsReturning(): bool`

Whether the connected server supports the RETURNING clause on INSERT/UPDATE/DELETE.

PostgreSQL supports it natively. MySQL 8.0.27+, MariaDB 10.5.0+ and SQLite 3.35+
also support it. Older servers must fall back to lastInsertId() for ID backfilling.

**➡️ Return value**

- Type: bool


---

### getDriver() · [source](../../src/Db/Database.php#L678)

`public function getDriver(): string`

Return the lowercase database driver name extracted from the DSN (e.g. "mysql", "pgsql", "sqlite").

**➡️ Return value**

- Type: string
- Description: Driver name.



---

[Back to the Index ⤴](README.md)
