# 🧩 Class: DatabaseOperationFailed

**Full name:** [Azera\Db\Event\DatabaseOperationFailed](../../src/Db/Event/DatabaseOperationFailed.php)

Dispatched when a PDO exception is caught and being processed by
[`Database::processPdoException()`](Db_Database.md#processpdoexception).

Listeners can use this for error logging, alerting, or metrics. The
exception may be re-thrown after processing; this event fires before
that decision is made.

The `$operation` identifies which database operation failed
(e.g. `query`, `prepare`, `execute`, `beginTransaction`, `commit`,
`rollback`). The `$sql` and `$params` are only populated
when the failing operation had a SQL statement in scope.

## 🌍 Public Properties

- `public readonly` PDOException `$exception` · [source](../../src/Db/Event/DatabaseOperationFailed.php)
- `public readonly` string `$operation` · [source](../../src/Db/Event/DatabaseOperationFailed.php)
- `public readonly` string|null `$sql` · [source](../../src/Db/Event/DatabaseOperationFailed.php)
- `public readonly` array|null `$params` · [source](../../src/Db/Event/DatabaseOperationFailed.php)
- `public readonly` [Database](Db_Database.md) `$database` · [source](../../src/Db/Event/DatabaseOperationFailed.php)

## 🚀 Public methods

### __construct() · [source](../../src/Db/Event/DatabaseOperationFailed.php#L23)

`public function __construct(Azera\Db\Database $database, PDOException $exception, string $operation, string|null $sql = null, array|null $params = null): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$database` | [Database](Db_Database.md) | - |  |
| `$exception` | PDOException | - |  |
| `$operation` | string | - |  |
| `$sql` | string\|null | `null` |  |
| `$params` | array\|null | `null` |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
