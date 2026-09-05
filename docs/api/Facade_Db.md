# 🧩 Class: Db

**Full name:** [Azera\Facade\Db](../../src/Facade/Db.php)

Thin static proxy over the SQL connection registry.

Plain static methods only — no magic container, no dynamic __callStatic
facade resolution. Every call goes through the AppContext singleton, so
it resolves the same shared connection per role the QB and ORM use.

## 🚀 Public methods

### query() · [source](../../src/Facade/Db.php#L21)

`public static function query(string|null $role = null): Azera\Db\Query`

Start a query builder on the default (or given) role's connection.

Delegates to the existing Query::new() — the QB stays the QB.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string\|null | `null` |  |

**➡️ Return value**

- Type: [Query](Db_Query.md)


---

### statement() · [source](../../src/Facade/Db.php#L31)

`public static function statement(string $sql, array|null $params = null, string|null $role = null): mixed`

Raw SQL through the tracked connection (events fire).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$sql` | string | - |  |
| `$params` | array\|null | `null` |  |
| `$role` | string\|null | `null` |  |

**➡️ Return value**

- Type: mixed


---

### connection() · [source](../../src/Facade/Db.php#L39)

`public static function connection(string|null $role = null): Azera\Db\Database`

Connection for a role (default when null).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string\|null | `null` |  |

**➡️ Return value**

- Type: [Database](Db_Database.md)


---

### transaction() · [source](../../src/Facade/Db.php#L49)

`public static function transaction(callable $fn, string|null $role = null): mixed`

Transaction run: BEGIN -> callback -> COMMIT; ROLLBACK on throw.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$fn` | callable | - |  |
| `$role` | string\|null | `null` |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
