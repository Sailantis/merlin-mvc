# 🧩 Class: Cookie

**Full name:** [Azera\Http\Cookie](../../src/Http/Cookie.php)

Represents a single HTTP cookie with optional transparent encryption.

Use the static `make()` factory or construct directly, then call
`send()` to emit the Set-Cookie header. Read the cookie value with
`value()`, which handles decryption automatically.

## 📌 Public Constants

- **CIPHER_CHACHA20_POLY1305** = `'chacha20-poly1305'`
- **CIPHER_AES_256_GCM** = `'aes-256-gcm'`
- **CIPHER_AUTO** = `'auto'`

## 🚀 Public methods

### make() · [source](../../src/Http/Cookie.php#L44)

`public static function make(string $name, mixed $value = null, int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httpOnly = true): static`

Create a new Cookie instance with the given parameters.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | The name of the cookie. |
| `$value` | mixed | `null` | The value of the cookie (optional). |
| `$expires` | int | `0` | Expiration timestamp (optional). |
| `$path` | string | `'/'` | Path for which the cookie is valid (optional). |
| `$domain` | string | `''` | Domain for which the cookie is valid (optional). |
| `$secure` | bool | `false` | Whether the cookie should only be sent over HTTPS (optional). |
| `$httpOnly` | bool | `true` | Whether the cookie should be inaccessible to JavaScript (optional). |

**➡️ Return value**

- Type: static
- Description: A new Cookie instance.


---

### __construct() · [source](../../src/Http/Cookie.php#L69)

`public function __construct(string $name, mixed $value = null, int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httpOnly = true): mixed`

Create a new Cookie instance.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Cookie name. |
| `$value` | mixed | `null` | Initial value (null means "not yet loaded"). |
| `$expires` | int | `0` | Expiration timestamp (0 = session cookie). |
| `$path` | string | `'/'` | URL path scope. |
| `$domain` | string | `''` | Domain scope. |
| `$secure` | bool | `false` | Send over HTTPS only. |
| `$httpOnly` | bool | `true` | Inaccessible to JavaScript. |

**➡️ Return value**

- Type: mixed


---

### value() · [source](../../src/Http/Cookie.php#L100)

`public function value(mixed $default = null): mixed`

Read the cookie value, lazily loading it from $_COOKIE and decrypting if needed.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$default` | mixed | `null` | Value to return when the cookie is not present. |

**➡️ Return value**

- Type: mixed


---

### set() · [source](../../src/Http/Cookie.php#L128)

`public function set(mixed $value): static`

Set the cookie value (in memory; call `send()` to persist).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$value` | mixed | - | New value. |

**➡️ Return value**

- Type: static


---

### send() · [source](../../src/Http/Cookie.php#L144)

`public function send(): static`

Emit a Set-Cookie header with the current cookie configuration.

Encrypts the value first if encryption is enabled.

**➡️ Return value**

- Type: static


---

### delete() · [source](../../src/Http/Cookie.php#L168)

`public function delete(): void`

Delete the cookie by setting its expiration to the past.

**➡️ Return value**

- Type: void


---

### encrypted() · [source](../../src/Http/Cookie.php#L189)

`public function encrypted(bool $state = true): static`

Enable or disable transparent encryption for this cookie.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$state` | bool | `true` | True to enable encryption (default), false to disable. |

**➡️ Return value**

- Type: static


---

### cipher() · [source](../../src/Http/Cookie.php#L201)

`public function cipher(string $cipher): static`

Set the encryption cipher to use (one of the `Azera\Crypt`::CIPHER_* constants).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$cipher` | string | - | Cipher identifier. |

**➡️ Return value**

- Type: static


---

### key() · [source](../../src/Http/Cookie.php#L213)

`public function key(string|null $key): static`

Set the encryption key. Defaults to a key derived from PHP's uname when null.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string\|null | - | Encryption key or null to use the default key. |

**➡️ Return value**

- Type: static


---

### name() · [source](../../src/Http/Cookie.php#L245)

`public function name(): string`

Get the cookie name.

**➡️ Return value**

- Type: string
- Description: Cookie name.


---

### expires() · [source](../../src/Http/Cookie.php#L256)

`public function expires(int $timestamp): static`

Set the expiration timestamp.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$timestamp` | int | - | Unix timestamp (0 = session cookie). |

**➡️ Return value**

- Type: static


---

### path() · [source](../../src/Http/Cookie.php#L268)

`public function path(string $path): static`

Set the URL path scope for the cookie.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$path` | string | - | URL path (e.g. "/"). |

**➡️ Return value**

- Type: static


---

### domain() · [source](../../src/Http/Cookie.php#L280)

`public function domain(string $domain): static`

Set the domain scope for the cookie.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$domain` | string | - | Domain (e.g. ".example.com"). |

**➡️ Return value**

- Type: static


---

### secure() · [source](../../src/Http/Cookie.php#L292)

`public function secure(bool $state): static`

Restrict the cookie to HTTPS connections only.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$state` | bool | - | True to require HTTPS. |

**➡️ Return value**

- Type: static


---

### httpOnly() · [source](../../src/Http/Cookie.php#L304)

`public function httpOnly(bool $state): static`

Make the cookie inaccessible to JavaScript (HttpOnly flag).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$state` | bool | - | True to set the HttpOnly flag. |

**➡️ Return value**

- Type: static


---

### __toString() · [source](../../src/Http/Cookie.php#L315)

`public function __toString(): string`

Return the cookie value as a string (useful for string-casting).

**➡️ Return value**

- Type: string
- Description: Cookie value, or empty string when not set.


---

### getAvailableCipher() · [source](../../src/Http/Cookie.php#L404)

`public static function getAvailableCipher(): mixed`

Get the best available cipher (prefers Sodium over OpenSSL)

**➡️ Return value**

- Type: mixed

**⚠️ Throws**

- Exception



---

[Back to the Index ⤴](README.md)
