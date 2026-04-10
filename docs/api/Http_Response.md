# 🧩 Class: Response

**Full name:** [Merlin\Http\Response](../../src/Http/Response.php)

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

### setStatus() · [source](../../src/Http/Response.php#L33)

`public function setStatus(int $code): static`

Set the HTTP status code.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$code` | int | - | HTTP status code (e.g. 200, 404). |

**➡️ Return value**

- Type: static


---

### setHeader() · [source](../../src/Http/Response.php#L46)

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

### setHeaders() · [source](../../src/Http/Response.php#L58)

`public function setHeaders(array $headers): static`

Set multiple response headers.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$headers` | array | - | Associative array of headers (e.g. ["Content-Type" => "application/json"]). |

**➡️ Return value**

- Type: static


---

### write() · [source](../../src/Http/Response.php#L70)

`public function write(string $text): static`

Append text to the response body.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | - | Content to append. |

**➡️ Return value**

- Type: static


---

### send() · [source](../../src/Http/Response.php#L79)

`public function send(): void`

Send the response: emit the status code, headers, and body.

**➡️ Return value**

- Type: void


---

### json() · [source](../../src/Http/Response.php#L97)

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

### text() · [source](../../src/Http/Response.php#L113)

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

### html() · [source](../../src/Http/Response.php#L129)

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

### redirect() · [source](../../src/Http/Response.php#L145)

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

### status() · [source](../../src/Http/Response.php#L160)

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
