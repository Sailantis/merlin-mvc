# 🧩 Class: ReconnectAttempt

**Full name:** [Azera\Db\Event\ReconnectAttempt](../../src/Db/Event/ReconnectAttempt.php)

Dispatched before each reconnection attempt in
[`Database::handleReconnect()`](Db_Database.md#handlereconnect).

## 🌍 Public Properties

- `public readonly` int `$attempt` · [source](../../src/Db/Event/ReconnectAttempt.php)
- `public readonly` float `$delaySeconds` · [source](../../src/Db/Event/ReconnectAttempt.php)
- `public readonly` Throwable|null `$cause` · [source](../../src/Db/Event/ReconnectAttempt.php)
- `public readonly` [Database](Db_Database.md) `$database` · [source](../../src/Db/Event/ReconnectAttempt.php)

## 🚀 Public methods

### __construct() · [source](../../src/Db/Event/ReconnectAttempt.php#L14)

`public function __construct(Azera\Db\Database $database, int $attempt, float $delaySeconds, Throwable|null $cause): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$database` | [Database](Db_Database.md) | - |  |
| `$attempt` | int | - |  |
| `$delaySeconds` | float | - |  |
| `$cause` | Throwable\|null | - |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
