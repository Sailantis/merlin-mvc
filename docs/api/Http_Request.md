# 🧩 Class: Request

**Full name:** [Azera\Http\Request](../../src/Http/Request.php)

Class Request
A simple HTTP request handler that abstracts away PHP's superglobals and provides convenient methods to access request data.

It also handles method overrides, proxy headers, content negotiation, and file uploads in a consistent way.

## 🚀 Public methods

### __construct() · [source](../../src/Http/Request.php#L25)

`public function __construct(array|null $server = null, array|null $get = null, array|null $post = null, array|null $files = null, bool $trustProxyHeaders = false): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$server` | array\|null | `null` |  |
| `$get` | array\|null | `null` |  |
| `$post` | array\|null | `null` |  |
| `$files` | array\|null | `null` |  |
| `$trustProxyHeaders` | bool | `false` |  |

**➡️ Return value**

- Type: mixed


---

### body() · [source](../../src/Http/Request.php#L46)

`public function body(): string`

Get the raw request body
Caches the body since php://input can only be read once

**➡️ Return value**

- Type: string


---

### jsonBody() · [source](../../src/Http/Request.php#L60)

`public function jsonBody(bool $assoc = true): mixed`

Get and parse JSON request body

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$assoc` | bool | `true` | When true, returns associative arrays. When false, returns objects |

**➡️ Return value**

- Type: mixed
- Description: Returns the parsed JSON data, or null on error

**⚠️ Throws**

- RuntimeException  if the JSON body cannot be parsed


---

### input() · [source](../../src/Http/Request.php#L80)

`public function input(string|null $name = null, mixed $default = null): mixed`

Get an input parameter from the request (POST takes precedence over GET)

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string\|null | `null` |  |
| `$default` | mixed | `null` |  |

**➡️ Return value**

- Type: mixed


---

### query() · [source](../../src/Http/Request.php#L94)

`public function query(string|null $name = null, mixed $default = null): mixed`

Get a query parameter from the request

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string\|null | `null` |  |
| `$default` | mixed | `null` |  |

**➡️ Return value**

- Type: mixed


---

### get() · [source](../../src/Http/Request.php#L108)

`public function get(string|null $name = null, mixed $default = null): mixed`

Get a GET parameter from the request

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string\|null | `null` |  |
| `$default` | mixed | `null` |  |

**➡️ Return value**

- Type: mixed


---

### post() · [source](../../src/Http/Request.php#L122)

`public function post(string|null $name = null, mixed $default = null): mixed`

Get a POST parameter from the request

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string\|null | `null` |  |
| `$default` | mixed | `null` |  |

**➡️ Return value**

- Type: mixed


---

### server() · [source](../../src/Http/Request.php#L136)

`public function server(string|null $name = null, mixed $default = null): mixed`

Get a server variable from the request

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string\|null | `null` |  |
| `$default` | mixed | `null` |  |

**➡️ Return value**

- Type: mixed


---

### header() · [source](../../src/Http/Request.php#L150)

`public function header(string $name, mixed $default = null): mixed`

Get a request header value

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | The name of the header (case-insensitive) |
| `$default` | mixed | `null` | The default value to return if the header is not present |

**➡️ Return value**

- Type: mixed
- Description: The header value, or the default if not present


---

### hasInput() · [source](../../src/Http/Request.php#L160)

`public function hasInput(string $name): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |

**➡️ Return value**

- Type: bool


---

### hasQuery() · [source](../../src/Http/Request.php#L165)

`public function hasQuery(string $name): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |

**➡️ Return value**

- Type: bool


---

### hasPost() · [source](../../src/Http/Request.php#L170)

`public function hasPost(string $name): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |

**➡️ Return value**

- Type: bool


---

### method() · [source](../../src/Http/Request.php#L179)

`public function method(): string`

Get the HTTP method of the request, accounting for method overrides in POST requests

**➡️ Return value**

- Type: string


---

### isPost() · [source](../../src/Http/Request.php#L201)

`public function isPost(): bool`

Checks whether the request method is POST

**➡️ Return value**

- Type: bool


---

### scheme() · [source](../../src/Http/Request.php#L210)

`public function scheme(): string`

Get the request scheme (http or https)

**➡️ Return value**

- Type: string


---

### isSecure() · [source](../../src/Http/Request.php#L226)

`public function isSecure(): bool`

Checks whether request has been made using HTTPS

**➡️ Return value**

- Type: bool


---

### host() · [source](../../src/Http/Request.php#L235)

`public function host(): string`

Get the host name of the request, accounting for proxy headers and Host header

**➡️ Return value**

- Type: string


---

### port() · [source](../../src/Http/Request.php#L253)

`public function port(): int`

Get the port number of the request, accounting for proxy headers and Host header

**➡️ Return value**

- Type: int


---

### uri() · [source](../../src/Http/Request.php#L276)

`public function uri(): string`

Get the full URI of the request

**➡️ Return value**

- Type: string


---

### path() · [source](../../src/Http/Request.php#L285)

`public function path(): string`

Get the path component of the request URI (without query string)

**➡️ Return value**

- Type: string


---

### clientIp() · [source](../../src/Http/Request.php#L296)

`public function clientIp(bool $trustForwarded = false): string|false`

Get the client IP address, accounting for proxy headers if trusted

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$trustForwarded` | bool | `false` |  |

**➡️ Return value**

- Type: string|false


---

### acceptableContent() · [source](../../src/Http/Request.php#L379)

`public function acceptableContent(bool $sort = false): array`

Get the list of acceptable content types from the Accept header, with quality factors

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$sort` | bool | `false` | Whether to sort by quality (highest first) |

**➡️ Return value**

- Type: array
- Description: An array of ['accept' => string, 'quality' => float, ...] entries


---

### bestAccept() · [source](../../src/Http/Request.php#L388)

`public function bestAccept(): string`

Get the best acceptable content type from the Accept header

**➡️ Return value**

- Type: string


---

### languages() · [source](../../src/Http/Request.php#L398)

`public function languages(bool $sort = false): array`

Get the list of acceptable languages from the Accept-Language header, with quality factors

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$sort` | bool | `false` | Whether to sort by quality (highest first) |

**➡️ Return value**

- Type: array
- Description: An array of ['language' => string, 'quality' => float, ...] entries


---

### bestLanguage() · [source](../../src/Http/Request.php#L407)

`public function bestLanguage(): string`

Get the best acceptable language from the Accept-Language header

**➡️ Return value**

- Type: string


---

### encodings() · [source](../../src/Http/Request.php#L417)

`public function encodings(bool $sort = false): array`

Get the list of acceptable encodings from the Accept-Encoding header, with quality factors

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$sort` | bool | `false` | Whether to sort by quality (highest first) |

**➡️ Return value**

- Type: array
- Description: An array of ['encoding' => string, 'quality' => float, ...] entries


---

### bestEncoding() · [source](../../src/Http/Request.php#L426)

`public function bestEncoding(): string`

Get the best acceptable encoding from the Accept-Encoding header

**➡️ Return value**

- Type: string


---

### charsets() · [source](../../src/Http/Request.php#L436)

`public function charsets(bool $sort = false): array`

Get the list of acceptable charsets from the Accept-Charset header, with quality factors

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$sort` | bool | `false` | Whether to sort by quality (highest first) |

**➡️ Return value**

- Type: array
- Description: An array of ['charset' => string, 'quality' => float, ...] entries


---

### bestCharset() · [source](../../src/Http/Request.php#L445)

`public function bestCharset(): string`

Get the best acceptable charset from the Accept-Charset header

**➡️ Return value**

- Type: string


---

### isJson() · [source](../../src/Http/Request.php#L491)

`public function isJson(): bool`

Checks whether the request expects a JSON response based on Content-Type or Accept headers

**➡️ Return value**

- Type: bool


---

### isAjax() · [source](../../src/Http/Request.php#L508)

`public function isAjax(): bool`

Checks whether the request is an AJAX request based on X-Requested-With header or if it expects JSON

**➡️ Return value**

- Type: bool


---

### basicAuth() · [source](../../src/Http/Request.php#L520)

`public function basicAuth(): array|null`

Get Basic Auth credentials from the request, accounting for different server configurations

**➡️ Return value**

- Type: array|null
- Description: Returns ['username' => string, 'password' => string] or null if not present


---

### authorization() · [source](../../src/Http/Request.php#L554)

`public function authorization(): array|null`

Get any HTTP auth scheme from the request

**➡️ Return value**

- Type: array|null
- Description: Returns ['scheme' => string, 'token' => string] or null if not present


---

### userAgent() · [source](../../src/Http/Request.php#L579)

`public function userAgent(): string`

Get the User-Agent string from the request headers

**➡️ Return value**

- Type: string


---

### contentType() · [source](../../src/Http/Request.php#L588)

`public function contentType(): string`

Get the Content-Type header from the request

**➡️ Return value**

- Type: string


---

### file() · [source](../../src/Http/Request.php#L641)

`public function file(string $key): Azera\Http\UploadedFile|null`

Get the first uploaded file for a given field name, or null if not present

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - |  |

**➡️ Return value**

- Type: [UploadedFile](Http_UploadedFile.md)|null


---

### files() · [source](../../src/Http/Request.php#L659)

`public function files(string $key): array`

Get all uploaded files for a given field name, or an empty array if not present

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - |  |

**➡️ Return value**

- Type: array



---

[Back to the Index ⤴](README.md)
