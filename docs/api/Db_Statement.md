# 🧩 Class: Statement

**Full name:** [Azera\Db\Statement](../../src/Db/Statement.php)

A prepared statement bound to a Database connection.

Unlike the legacy Database::prepare()/execute() pair, a PreparedStatement
owns its PDOStatement, so any number of statements can be prepared and
executed concurrently without clobbering a single shared slot.

## 🚀 Public methods

### __construct() · [source](../../src/Db/Statement.php#L32)

`public function __construct(Azera\Db\Database $db, PDOStatement $statement, string $sql): mixed`

Create a new PreparedStatement wrapping an already-prepared PDO statement.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$db` | [Database](Db_Database.md) | - | Database connection used to prepare the statement. |
| `$statement` | PDOStatement | - | The prepared PDO statement. |
| `$sql` | string | - | The original SQL string (used for error reporting). |

**➡️ Return value**

- Type: mixed


---

### execute() · [source](../../src/Db/Statement.php#L49)

`public function execute(array $params = []): Azera\Db\Statement|bool`

Execute the statement with the given bound parameters.

Deadlocks and connection-loss errors are retried through the connection's
auto-reconnect logic, mirroring Database::query().

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$params` | array | `[]` | Optional parameters to bind for this execution. |

**➡️ Return value**

- Type: [Statement](Db_Statement.md)|bool
- Description: Returns $this for chaining when the statement returns rows, true otherwise.

**⚠️ Throws**

- Exception  On database errors.


---

### rowCount() · [source](../../src/Db/Statement.php#L80)

`public function rowCount(): int`

Return the number of rows affected by the last execution.

**➡️ Return value**

- Type: int


---

### columnCount() · [source](../../src/Db/Statement.php#L89)

`public function columnCount(): int`

Return the number of columns in the result set.

**➡️ Return value**

- Type: int


---

### getStatement() · [source](../../src/Db/Statement.php#L98)

`public function getStatement(): PDOStatement`

Return the underlying PDO statement instance.

**➡️ Return value**

- Type: PDOStatement


---

### getSql() · [source](../../src/Db/Statement.php#L107)

`public function getSql(): string`

Return the original SQL string this statement was prepared from.

**➡️ Return value**

- Type: string



---

[Back to the Index ⤴](README.md)
