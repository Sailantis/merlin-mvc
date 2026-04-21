# 🧩 Class: Request

**Full name:** [Merlin\Http\Request](../../src/Http/Request.php)

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

### getBody() · [source](../../src/Http/Request.php#L47)

`public function getBody(): string`

Get the raw request body
Caches the body since php://input can only be read once

**➡️ Return value**

- Type: string


---

### getJsonBody() · [source](../../src/Http/Request.php#L61)

`public function getJsonBody(bool $assoc = true): mixed`

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

### input() · [source](../../src/Http/Request.php#L81)

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

### query() · [source](../../src/Http/Request.php#L95)

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

### post() · [source](../../src/Http/Request.php#L109)

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

### server() · [source](../../src/Http/Request.php#L123)

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

### hasInput() · [source](../../src/Http/Request.php#L134)

`public function hasInput(string $name): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |

**➡️ Return value**

- Type: bool


---

### hasQuery() · [source](../../src/Http/Request.php#L139)

`public function hasQuery(string $name): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |

**➡️ Return value**

- Type: bool


---

### hasPost() · [source](../../src/Http/Request.php#L144)

`public function hasPost(string $name): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |

**➡️ Return value**

- Type: bool


---

### getMethod() · [source](../../src/Http/Request.php#L153)

`public function getMethod(): string`

Get the HTTP method of the request, accounting for method overrides in POST requests

**➡️ Return value**

- Type: string


---

### isPost() · [source](../../src/Http/Request.php#L175)

`public function isPost(): bool`

Checks whether the request method is POST

**➡️ Return value**

- Type: bool


---

### getScheme() · [source](../../src/Http/Request.php#L184)

`public function getScheme(): string`

Get the request scheme (http or https)

**➡️ Return value**

- Type: string


---

### isSecure() · [source](../../src/Http/Request.php#L200)

`public function isSecure(): bool`

Checks whether request has been made using HTTPS

**➡️ Return value**

- Type: bool


---

### getHost() · [source](../../src/Http/Request.php#L209)

`public function getHost(): string`

Get the host name of the request, accounting for proxy headers and Host header

**➡️ Return value**

- Type: string


---

### getPort() · [source](../../src/Http/Request.php#L227)

`public function getPort(): int`

Get the port number of the request, accounting for proxy headers and Host header

**➡️ Return value**

- Type: int


---

### getUri() · [source](../../src/Http/Request.php#L250)

`public function getUri(): string`

Get the full URI of the request

**➡️ Return value**

- Type: string


---

### getPath() · [source](../../src/Http/Request.php#L259)

`public function getPath(): string`

Get the path component of the request URI (without query string)

**➡️ Return value**

- Type: string


---

### getClientIp() · [source](../../src/Http/Request.php#L270)

`public function getClientIp(bool $trustForwarded = false): string|false`

Get the client IP address, accounting for proxy headers if trusted

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$trustForwarded` | bool | `false` |  |

**➡️ Return value**

- Type: string|false


---

### getAcceptableContent() · [source](../../src/Http/Request.php#L354)

`public function getAcceptableContent(bool $sort = false): array`

Get the list of acceptable content types from the Accept header, with quality factors

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$sort` | bool | `false` | Whether to sort by quality (highest first) |

**➡️ Return value**

- Type: array
- Description: An array of ['accept' => string, 'quality' => float, ...] entries


---

### getBestAccept() · [source](../../src/Http/Request.php#L363)

`public function getBestAccept(): string`

Get the best acceptable content type from the Accept header

**➡️ Return value**

- Type: string


---

### getLanguages() · [source](../../src/Http/Request.php#L373)

`public function getLanguages(bool $sort = false): array`

Get the list of acceptable languages from the Accept-Language header, with quality factors

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$sort` | bool | `false` | Whether to sort by quality (highest first) |

**➡️ Return value**

- Type: array
- Description: An array of ['language' => string, 'quality' => float, ...] entries


---

### getBestLanguage() · [source](../../src/Http/Request.php#L382)

`public function getBestLanguage(): string`

Get the best acceptable language from the Accept-Language header

**➡️ Return value**

- Type: string


---

### getEncodings() · [source](../../src/Http/Request.php#L392)

`public function getEncodings(bool $sort = false): array`

Get the list of acceptable encodings from the Accept-Encoding header, with quality factors

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$sort` | bool | `false` | Whether to sort by quality (highest first) |

**➡️ Return value**

- Type: array
- Description: An array of ['encoding' => string, 'quality' => float, ...] entries


---

### getBestEncoding() · [source](../../src/Http/Request.php#L401)

`public function getBestEncoding(): string`

Get the best acceptable encoding from the Accept-Encoding header

**➡️ Return value**

- Type: string


---

### getCharsets() · [source](../../src/Http/Request.php#L411)

`public function getCharsets(bool $sort = false): array`

Get the list of acceptable charsets from the Accept-Charset header, with quality factors

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$sort` | bool | `false` | Whether to sort by quality (highest first) |

**➡️ Return value**

- Type: array
- Description: An array of ['charset' => string, 'quality' => float, ...] entries


---

### getBestCharset() · [source](../../src/Http/Request.php#L420)

`public function getBestCharset(): string`

Get the best acceptable charset from the Accept-Charset header

**➡️ Return value**

- Type: string


---

### isJson() · [source](../../src/Http/Request.php#L466)

`public function isJson(): bool`

Checks whether the request expects a JSON response based on Content-Type or Accept headers

**➡️ Return value**

- Type: bool


---

### isAjax() · [source](../../src/Http/Request.php#L483)

`public function isAjax(): bool`

Checks whether the request is an AJAX request based on X-Requested-With header or if it expects JSON

**➡️ Return value**

- Type: bool


---

### getBasicAuth() · [source](../../src/Http/Request.php#L495)

`public function getBasicAuth(): array|null`

Get Basic Auth credentials from the request, accounting for different server configurations

**➡️ Return value**

- Type: array|null
- Description: Returns ['username' => string, 'password' => string] or null if not present


---

### getAuthorization() · [source](../../src/Http/Request.php#L529)

`public function getAuthorization(): array|null`

Get any HTTP auth scheme from the Authorization header

**➡️ Return value**

- Type: array|null
- Description: Returns ['scheme' => string, 'token' => string] or null if not present


---

### getUserAgent() · [source](../../src/Http/Request.php#L554)

`public function getUserAgent(): string`

Get the User-Agent string from the request headers

**➡️ Return value**

- Type: string


---

### getContentType() · [source](../../src/Http/Request.php#L563)

`public function getContentType(): string`

Get the Content-Type header from the request

**➡️ Return value**

- Type: string


---

### getFile() · [source](../../src/Http/Request.php#L616)

`public function getFile(string $key): Merlin\Http\UploadedFile|null`

Get the first uploaded file for a given field name, or null if not present

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - |  |

**➡️ Return value**

- Type: [UploadedFile](Http_UploadedFile.md)|null


---

### getFiles() · [source](../../src/Http/Request.php#L634)

`public function getFiles(string $key): array`

Get all uploaded files for a given field name, or an empty array if not present

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$key` | string | - |  |

**➡️ Return value**

- Type: array



---

[Back to the Index ⤴](README.md)
