# 🧩 Class: DatabaseExceptionOccurred

**Full name:** [Azera\Db\Event\DatabaseExceptionOccurred](../../src/Db/Event/DatabaseExceptionOccurred.php)

Dispatched when a PDO exception is caught and being processed by
[`Database::processPdoException()`](Db_Database.md#processpdoexception).

Listeners can use this for error logging, alerting, or metrics. The
exception may be re-thrown after processing; this event fires before
that decision is made.

## 🌍 Public Properties

- `public readonly` PDOException `$exception` · [source](../../src/Db/Event/DatabaseExceptionOccurred.php)
- `public readonly` string|null `$sql` · [source](../../src/Db/Event/DatabaseExceptionOccurred.php)
- `public readonly` array|null `$params` · [source](../../src/Db/Event/DatabaseExceptionOccurred.php)
- `public readonly` [Database](Db_Database.md) `$database` · [source](../../src/Db/Event/DatabaseExceptionOccurred.php)

## 🚀 Public methods

### __construct() · [source](../../src/Db/Event/DatabaseExceptionOccurred.php#L18)

`public function __construct(Azera\Db\Database $database, PDOException $exception, string|null $sql = null, array|null $params = null): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$database` | [Database](Db_Database.md) | - |  |
| `$exception` | PDOException | - |  |
| `$sql` | string\|null | `null` |  |
| `$params` | array\|null | `null` |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
