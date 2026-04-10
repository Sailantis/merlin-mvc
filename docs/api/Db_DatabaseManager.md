# 🧩 Class: DatabaseManager

**Full name:** [Merlin\Db\DatabaseManager](../../src/Db/DatabaseManager.php)

Manages multiple database connections (roles) and their factories.

This class allows the definition of multiple database connections (e.g. "default", "analytics", "logging") and retrieval of them by role. The first role defined will be used as the default when requesting the default connection, but it can be changed by calling setDefault(). Each role can be defined with either a Database instance or a factory callable that returns a Database instance. The factory will only be called once per role, and the resulting Database instance will be cached for future use.

## 🚀 Public methods

### set() · [source](../../src/Db/DatabaseManager.php#L32)

`public function set(string $role, Merlin\Db\Database|callable $factory): static`

Define a database connection for a specific role.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - | The name of the role (e.g. "default", "analytics") |
| `$factory` | [Database](Db_Database.md)\|callable | - | A factory callable that returns a Database instance, or a Database instance directly |

**➡️ Return value**

- Type: static


---

### addGlobalListener() · [source](../../src/Db/DatabaseManager.php#L52)

`public function addGlobalListener(callable $listener): static`

Add an event listener that will be attached to every database connection managed by this instance.

Listeners registered before a factory is resolved will be applied on first access.
Listeners registered after a connection is already resolved will be applied immediately.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$listener` | callable | - | A callable that receives (string $event, mixed ...$args) |

**➡️ Return value**

- Type: static


---

### addListener() · [source](../../src/Db/DatabaseManager.php#L70)

`public function addListener(string $role, callable $listener): static`

Add an event listener for a specific database role.

If the role's connection is already resolved, the listener is applied immediately.
If the role uses a factory that has not been called yet, the listener will be applied on first access.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - | The name of the role to listen on |
| `$listener` | callable | - | A callable that receives (string $event, mixed ...$args) |

**➡️ Return value**

- Type: static


---

### setDefault() · [source](../../src/Db/DatabaseManager.php#L86)

`public function setDefault(string $role): static`

Set the default database role to use when requesting the default connection. By default, the first defined role will be used as the default.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - | The name of the role to set as default |

**➡️ Return value**

- Type: static

**⚠️ Throws**

- RuntimeException  If the specified role is not defined


---

### has() · [source](../../src/Db/DatabaseManager.php#L102)

`public function has(string $role): bool`

Check if a database role is defined.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - | The name of the role to check |

**➡️ Return value**

- Type: bool
- Description: True if the role is defined, false otherwise


---

### get() · [source](../../src/Db/DatabaseManager.php#L114)

`public function get(string $role): Merlin\Db\Database`

Get the Database instance for a specific role.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - | The name of the role to retrieve |

**➡️ Return value**

- Type: [Database](Db_Database.md)
- Description: The Database instance for the specified role

**⚠️ Throws**

- RuntimeException  If the role is not defined or if the factory does not return a Database instance


---

### getOrDefault() · [source](../../src/Db/DatabaseManager.php#L153)

`public function getOrDefault(string $role): Merlin\Db\Database`

Get the Database instance for a specific role, or the default if the role is not defined.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - | The name of the role to retrieve |

**➡️ Return value**

- Type: [Database](Db_Database.md)
- Description: The Database instance for the specified role, or the default if not defined

**⚠️ Throws**

- RuntimeException  If no default database is configured


---

### getDefault() · [source](../../src/Db/DatabaseManager.php#L168)

`public function getDefault(): Merlin\Db\Database`

Get the default Database instance.

**➡️ Return value**

- Type: [Database](Db_Database.md)
- Description: The default Database instance

**⚠️ Throws**

- RuntimeException  If no default database is configured



---

[Back to the Index ⤴](README.md)
