# 🧩 Class: Response

**Full name:** [Azera\Http\Response](../../src/Http/Response.php)

Represents an HTTP response.

Build a response by chaining setters and finish by calling `send()`,
or use one of the static factory methods (`json()`, `html()`,
`redirect()`, etc.) for common cases.

## 🚀 Public methods

### __construct() · [source](../../src/Http/Response.php#L20)

`public function __construct(int $status = 200, array $headers = [], string $body = ''): mixed`

Create a new Response.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$status` | int | `200` | HTTP status code. |
| `$headers` | array | `[]` | Associative array of response headers. |
| `$body` | string | `''` | Response body. |

**➡️ Return value**

- Type: mixed


---

### setStatus() · [source](../../src/Http/Response.php#L32)

`public function setStatus(int $code): static`

Set the HTTP status code.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$code` | int | - | HTTP status code (e.g. 200, 404). |

**➡️ Return value**

- Type: static


---

### getStatus() · [source](../../src/Http/Response.php#L43)

`public function getStatus(): int`

Get the HTTP status code.

**➡️ Return value**

- Type: int


---

### getHeader() · [source](../../src/Http/Response.php#L55)

`public function getHeader(string $name, mixed $default = null): mixed`

Get a response header value, or a default when not set.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Header name. |
| `$default` | mixed | `null` | Default when the header is absent. |

**➡️ Return value**

- Type: mixed


---

### getHeaders() · [source](../../src/Http/Response.php#L65)

`public function getHeaders(): array`

Get all response headers.

**➡️ Return value**

- Type: array


---

### getBody() · [source](../../src/Http/Response.php#L73)

`public function getBody(): string`

Get the response body.

**➡️ Return value**

- Type: string


---

### setHeader() · [source](../../src/Http/Response.php#L85)

`public function setHeader(string $key, string $value): static`

Set a response header.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - | Header name (e.g. "Content-Type"). |
| `$value` | string | - | Header value. |

**➡️ Return value**

- Type: static


---

### setHeaders() · [source](../../src/Http/Response.php#L97)

`public function setHeaders(array $headers): static`

Set multiple response headers.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$headers` | array | - | Associative array of headers (e.g. ["Content-Type" => "application/json"]). |

**➡️ Return value**

- Type: static


---

### write() · [source](../../src/Http/Response.php#L109)

`public function write(string $text): static`

Append text to the response body.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | - | Content to append. |

**➡️ Return value**

- Type: static


---

### send() · [source](../../src/Http/Response.php#L118)

`public function send(): void`

Send the response: emit the status code, headers, and body.

**➡️ Return value**

- Type: void


---

### json() · [source](../../src/Http/Response.php#L136)

`public static function json(mixed $data, int $status = 200): static`

Create a JSON response.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$data` | mixed | - | Data to JSON-encode. |
| `$status` | int | `200` | HTTP status code (default 200). |

**➡️ Return value**

- Type: static


---

### text() · [source](../../src/Http/Response.php#L152)

`public static function text(string $text, int $status = 200): static`

Create a plain-text response.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | - | Response body. |
| `$status` | int | `200` | HTTP status code (default 200). |

**➡️ Return value**

- Type: static


---

### html() · [source](../../src/Http/Response.php#L168)

`public static function html(string $html, int $status = 200): static`

Create an HTML response.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$html` | string | - | HTML content. |
| `$status` | int | `200` | HTTP status code (default 200). |

**➡️ Return value**

- Type: static


---

### redirect() · [source](../../src/Http/Response.php#L184)

`public static function redirect(string $url, int $status = 302): static`

Create a redirect response.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$url` | string | - | URL to redirect to. |
| `$status` | int | `302` | HTTP redirect status code (default 302). |

**➡️ Return value**

- Type: static


---

### status() · [source](../../src/Http/Response.php#L199)

`public static function status(int $status): static`

Create a response with only a status code and an empty body.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$status` | int | - | HTTP status code. |

**➡️ Return value**

- Type: static



---

[Back to the Index ⤴](README.md)
