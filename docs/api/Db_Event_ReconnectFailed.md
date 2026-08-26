# 🧩 Class: ReconnectFailed

**Full name:** [Azera\Db\Event\ReconnectFailed](../../src/Db/Event/ReconnectFailed.php)

Dispatched when a reconnection attempt fails.

## 🌍 Public Properties

- `public readonly` Throwable `$exception` · [source](../../src/Db/Event/ReconnectFailed.php)
- `public readonly` int `$attempt` · [source](../../src/Db/Event/ReconnectFailed.php)
- `public readonly` [Database](Db_Database.md) `$database` · [source](../../src/Db/Event/ReconnectFailed.php)

## 🚀 Public methods

### __construct() · [source](../../src/Db/Event/ReconnectFailed.php#L13)

`public function __construct(Azera\Db\Database $database, Throwable $exception, int $attempt): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$database` | [Database](Db_Database.md) | - |  |
| `$exception` | Throwable | - |  |
| `$attempt` | int | - |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
