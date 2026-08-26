# 🧩 Class: QueryExecuted

**Full name:** [Azera\Db\Event\QueryExecuted](../../src/Db/Event/QueryExecuted.php)

Dispatched after a SQL query has been executed via [`Database::query()`](Db_Database.md#query).

Carries the executed SQL, bound parameters, and the wall-clock duration
in milliseconds. Useful for query logging, slow-query detection, and
debugging.

## 🌍 Public Properties

- `public readonly` string `$sql` · [source](../../src/Db/Event/QueryExecuted.php)
- `public readonly` array|null `$params` · [source](../../src/Db/Event/QueryExecuted.php)
- `public readonly` float `$durationMs` · [source](../../src/Db/Event/QueryExecuted.php)
- `public readonly` [Database](Db_Database.md) `$database` · [source](../../src/Db/Event/QueryExecuted.php)

## 🚀 Public methods

### __construct() · [source](../../src/Db/Event/QueryExecuted.php#L16)

`public function __construct(Azera\Db\Database $database, string $sql, array|null $params, float $durationMs): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$database` | [Database](Db_Database.md) | - |  |
| `$sql` | string | - |  |
| `$params` | array\|null | - |  |
| `$durationMs` | float | - |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
