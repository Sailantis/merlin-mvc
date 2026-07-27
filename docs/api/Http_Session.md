# 🧩 Class: Session

**Full name:** [Azera\Http\Session](../../src/Http/Session.php)

Wrapper around a PHP session array that provides typed accessors and
a clean API for reading, writing, and clearing session data.

## 🚀 Public methods

### __construct() · [source](../../src/Http/Session.php#L15)

`public function __construct(array &$store): mixed`

Create a new Session backed by the given store reference.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$store` | array | - | Reference to the underlying session array (typically $_SESSION). |

**➡️ Return value**

- Type: mixed


---

### get() · [source](../../src/Http/Session.php#L26)

`public function get(string $key, mixed $default = null): mixed`

Retrieve a value from the session.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - | Session key. |
| `$default` | mixed | `null` | Value to return when the key is not set. |

**➡️ Return value**

- Type: mixed


---

### set() · [source](../../src/Http/Session.php#L37)

`public function set(string $key, mixed $value): void`

Store a value in the session.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - | Session key. |
| `$value` | mixed | - | Value to store. |

**➡️ Return value**

- Type: void


---

### remove() · [source](../../src/Http/Session.php#L47)

`public function remove(string $key): void`

Remove a key from the session.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - | Session key to unset. |

**➡️ Return value**

- Type: void


---

### has() · [source](../../src/Http/Session.php#L58)

`public function has(string $key): bool`

Check whether a key exists in the session.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - | Session key. |

**➡️ Return value**

- Type: bool
- Description: True if the key is set and not null.


---

### clear() · [source](../../src/Http/Session.php#L66)

`public function clear(): void`

Remove all data from the session.

**➡️ Return value**

- Type: void



---

[Back to the Index ⤴](README.md)
