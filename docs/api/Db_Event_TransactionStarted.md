# 🧩 Class: TransactionStarted

**Full name:** [Azera\Db\Event\TransactionStarted](../../src/Db/Event/TransactionStarted.php)

Dispatched after a transaction (or savepoint) has been started via
[`Database::begin()`](Db_Database.md#begin).

## 🌍 Public Properties

- `public readonly` bool `$nesting` · [source](../../src/Db/Event/TransactionStarted.php)
- `public readonly` int `$level` · [source](../../src/Db/Event/TransactionStarted.php)
- `public readonly` [Database](Db_Database.md) `$database` · [source](../../src/Db/Event/TransactionStarted.php)

## 🚀 Public methods

### __construct() · [source](../../src/Db/Event/TransactionStarted.php#L13)

`public function __construct(Azera\Db\Database $database, bool $nesting, int $level): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$database` | [Database](Db_Database.md) | - |  |
| `$nesting` | bool | - |  |
| `$level` | int | - |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
