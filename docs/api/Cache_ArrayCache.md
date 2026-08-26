# 🧩 Class: ArrayCache

**Full name:** [Azera\Cache\ArrayCache](../../src/Cache/ArrayCache.php)

In-process cache backed by a PHP array.

Useful for development, testing, and short-lived processes. Values
are stored with an expiry timestamp (or null for no expiry). No
serialization overhead — values are kept as live references.

TTL handling:
  - int          → seconds from now
  - DateInterval → added to now
  - null         → no expiry (lives for the process lifetime)

Keys are validated to be non-empty, alphanumeric + `._-`, max 64 chars.

## 🚀 Public methods

### get() · [source](../../src/Cache/ArrayCache.php#L30)

`public function get(string $key, mixed $default = null): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - |  |
| `$default` | mixed | `null` |  |

**➡️ Return value**

- Type: mixed


---

### set() · [source](../../src/Cache/ArrayCache.php#L48)

`public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - |  |
| `$value` | mixed | - |  |
| `$ttl` | DateInterval\|int\|null | `null` |  |

**➡️ Return value**

- Type: bool


---

### delete() · [source](../../src/Cache/ArrayCache.php#L60)

`public function delete(string $key): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - |  |

**➡️ Return value**

- Type: bool


---

### clear() · [source](../../src/Cache/ArrayCache.php#L67)

`public function clear(): bool`

**➡️ Return value**

- Type: bool


---

### getMultiple() · [source](../../src/Cache/ArrayCache.php#L73)

`public function getMultiple(iterable $keys, mixed $default = null): iterable`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$keys` | iterable | - |  |
| `$default` | mixed | `null` |  |

**➡️ Return value**

- Type: iterable


---

### setMultiple() · [source](../../src/Cache/ArrayCache.php#L82)

`public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$values` | iterable | - |  |
| `$ttl` | DateInterval\|int\|null | `null` |  |

**➡️ Return value**

- Type: bool


---

### deleteMultiple() · [source](../../src/Cache/ArrayCache.php#L92)

`public function deleteMultiple(iterable $keys): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$keys` | iterable | - |  |

**➡️ Return value**

- Type: bool


---

### has() · [source](../../src/Cache/ArrayCache.php#L101)

`public function has(string $key): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - |  |

**➡️ Return value**

- Type: bool


---

### gc() · [source](../../src/Cache/ArrayCache.php#L123)

`public function gc(): void`

Remove all expired entries. Called opportunistically; not required
for correctness since expired entries are lazily purged on access.

**➡️ Return value**

- Type: void



---

[Back to the Index ⤴](README.md)
