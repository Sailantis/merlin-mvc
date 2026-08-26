# 🧩 Class: TransactionRolledBack

**Full name:** [Azera\Db\Event\TransactionRolledBack](../../src/Db/Event/TransactionRolledBack.php)

Dispatched after a transaction (or savepoint) has been rolled back via
[`Database::rollback()`](Db_Database.md#rollback).

## 🌍 Public Properties

- `public readonly` bool `$nesting` · [source](../../src/Db/Event/TransactionRolledBack.php)
- `public readonly` int `$level` · [source](../../src/Db/Event/TransactionRolledBack.php)
- `public readonly` [Database](Db_Database.md) `$database` · [source](../../src/Db/Event/TransactionRolledBack.php)

## 🚀 Public methods

### __construct() · [source](../../src/Db/Event/TransactionRolledBack.php#L13)

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
