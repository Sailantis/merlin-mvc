# 🧩 Class: NullCache

**Full name:** [Azera\Cache\NullCache](../../src/Cache/NullCache.php)

No-op cache that stores nothing and always reports a miss.

This is the default cache returned by [`AppContext::cache()`](AppContext.md#cache)
when no concrete cache has been registered. It allows calling code to
safely use `$ctx->cache()->get('key', $default)` and always receive
the default, without null-checks.

## 🚀 Public methods

### get() · [source](../../src/Cache/NullCache.php#L17)

`public function get(string $key, mixed $default = null): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - |  |
| `$default` | mixed | `null` |  |

**➡️ Return value**

- Type: mixed


---

### set() · [source](../../src/Cache/NullCache.php#L22)

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

### delete() · [source](../../src/Cache/NullCache.php#L27)

`public function delete(string $key): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - |  |

**➡️ Return value**

- Type: bool


---

### clear() · [source](../../src/Cache/NullCache.php#L32)

`public function clear(): bool`

**➡️ Return value**

- Type: bool


---

### getMultiple() · [source](../../src/Cache/NullCache.php#L37)

`public function getMultiple(iterable $keys, mixed $default = null): iterable`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$keys` | iterable | - |  |
| `$default` | mixed | `null` |  |

**➡️ Return value**

- Type: iterable


---

### setMultiple() · [source](../../src/Cache/NullCache.php#L46)

`public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$values` | iterable | - |  |
| `$ttl` | DateInterval\|int\|null | `null` |  |

**➡️ Return value**

- Type: bool


---

### deleteMultiple() · [source](../../src/Cache/NullCache.php#L51)

`public function deleteMultiple(iterable $keys): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$keys` | iterable | - |  |

**➡️ Return value**

- Type: bool


---

### has() · [source](../../src/Cache/NullCache.php#L56)

`public function has(string $key): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - |  |

**➡️ Return value**

- Type: bool



---

[Back to the Index ⤴](README.md)
