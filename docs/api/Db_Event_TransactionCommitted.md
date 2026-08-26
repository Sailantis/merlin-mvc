# 🧩 Class: TransactionCommitted

**Full name:** [Azera\Db\Event\TransactionCommitted](../../src/Db/Event/TransactionCommitted.php)

Dispatched after a transaction (or savepoint) has been committed via
[`Database::commit()`](Db_Database.md#commit).

## 🌍 Public Properties

- `public readonly` bool `$nesting` · [source](../../src/Db/Event/TransactionCommitted.php)
- `public readonly` int `$level` · [source](../../src/Db/Event/TransactionCommitted.php)
- `public readonly` [Database](Db_Database.md) `$database` · [source](../../src/Db/Event/TransactionCommitted.php)

## 🚀 Public methods

### __construct() · [source](../../src/Db/Event/TransactionCommitted.php#L13)

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
