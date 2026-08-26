# 🧩 Class: StatementExecuted

**Full name:** [Azera\Db\Event\StatementExecuted](../../src/Db/Event/StatementExecuted.php)

Dispatched after a previously prepared statement has been executed via
[`Database::execute()`](Db_Database.md#execute).

## 🌍 Public Properties

- `public readonly` array `$params` · [source](../../src/Db/Event/StatementExecuted.php)
- `public readonly` float `$durationMs` · [source](../../src/Db/Event/StatementExecuted.php)
- `public readonly` [Database](Db_Database.md) `$database` · [source](../../src/Db/Event/StatementExecuted.php)

## 🚀 Public methods

### __construct() · [source](../../src/Db/Event/StatementExecuted.php#L13)

`public function __construct(Azera\Db\Database $database, array $params, float $durationMs): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$database` | [Database](Db_Database.md) | - |  |
| `$params` | array | - |  |
| `$durationMs` | float | - |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
