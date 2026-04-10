# HTTP Request

`Merlin\Http\Request` provides normalized access to all incoming request data: query parameters, POST fields, uploaded files, headers, and more.

Obtain the request object from `AppContext` rather than instantiating it directly:

```php
$request = \Merlin\AppContext::instance()->request();
// or inside a controller:
$request = $this->request();
```

---

## Reading Input

All input accessors accept an optional name and default value. Omit the name to get the full array.

```php
// Input ($_GET + $_POST)
$q       = $request->input('q');
$all     = $request->input();

// Query string ($_GET)
$page    = $request->query('page', 1);
$allGet  = $request->query();

// POST body ($_POST)
$email   = $request->post('email');
$allPost = $request->post();

// Raw $_SERVER value
$ua = $request->server('HTTP_USER_AGENT', '');
```

### Checking Parameter Existence

Use the `has*` helpers instead of comparing the return value to `null`, since a field might legitimately hold `null` or `0`.

```php
$request->hasInput('token');        // isset in $_GET + $_POST
$request->hasQuery('page');    // isset in $_GET
$request->hasPost('_method');  // isset in $_POST
$request->hasServer('HTTPS');  // isset in $_SERVER
```

---

## Method and URL

```php
$method = $request->getMethod();   // 'GET', 'POST', 'PUT', …
$uri    = $request->getUri();      // '/users/5?tab=profile'
$path   = $request->getPath();     // '/users/5'

$request->isPost();    // true when method is POST
$request->isSecure();  // true when HTTPS
$request->isJson();    // true for Content-Type: application/json or Accept: application/json
$request->isAjax();    // true for fetch/axios/jQuery XHR (see note below)
```

> **Method override** – `getMethod()` recognises an `X-HTTP-Method-Override` header sent with a POST request and returns the overridden method (e.g. `'PUT'`), which allows method tunnelling through form submissions.

> **AJAX detection** – `isAjax()` returns `true` when any of the following is present: `Content-Type: application/json`, `Accept: application/json`, or `X-Requested-With: XMLHttpRequest`.

---

## Server and Client Information

```php
$host       = $request->getHost();          // 'example.com' or 'example.com:8080'
$scheme     = $request->getScheme();            // 'http' or 'https'
$port       = $request->getPort();              // 443
$userAgent  = $request->getUserAgent();
$clientIp   = $request->getClientIp();     // REMOTE_ADDR
$clientIp   = $request->getClientIp(true); // trust X-Forwarded-For / HTTP_CLIENT_IP

$contentType = $request->getContentType();
```

---

## Content Negotiation

Parse `Accept`, `Accept-Language`, and `Accept-Charset` headers into quality-sorted arrays.

```php
// Accept
$types       = $request->getAcceptableContent();       // unsorted
$types       = $request->getAcceptableContent(true);   // sorted by quality
$bestType    = $request->getBestAccept();               // e.g. 'text/html'

// Accept-Language
$languages   = $request->getLanguages();
$bestLang    = $request->getBestLanguage();             // e.g. 'en'

// Accept-Charset
$charsets    = $request->getClientCharsets();
$bestCharset = $request->getBestCharset();              // e.g. 'utf-8'
```

Each entry in the returned arrays contains the value and its `quality` key (0–1).

---

## JSON Body

```php
$raw  = $request->getBody();             // raw php://input string (cached)
$data = $request->getJsonBody();         // decoded as associative array (default)
$obj  = $request->getJsonBody(false);    // decoded as stdClass objects
```

`getJsonBody()` throws `\RuntimeException` if the body is not valid JSON.

---

## HTTP Authentication

```php
// HTTP Basic Auth
$auth = $request->getBasicAuth();
// ['username' => '...', 'password' => '...'] or null

// Any HTTP auth scheme (e.g. "Bearer", "Digest", "Custom")
$auth = $request->getAuthorization();
// ['scheme' => '...', 'token' => '...'] or null
```

---

## File Uploads

`getFile()` returns a single `UploadedFile` (or `null`). `getFiles()` always returns an array, which is convenient for multi-file inputs.

```php
$file = $request->getFile('avatar');
if ($file && $file->isValid()) {
    $file->moveTo(__DIR__ . '/uploads/' . $file->getClientFilename());
}

// Multi-file input (<input type="file" name="docs[]" multiple>)
foreach ($request->getFiles('docs') as $doc) {
    if ($doc->isValid()) {
        $doc->moveTo('/storage/' . $doc->getClientFilename());
    }
}
```

`UploadedFile` API:

| Method                 | Returns  | Description                                                 |
| ---------------------- | -------- | ----------------------------------------------------------- |
| `isValid()`            | `bool`   | `true` when `UPLOAD_ERR_OK`                                 |
| `getClientFilename()`  | `string` | Original filename from the browser (sanitise before use)    |
| `getClientMediaType()` | `string` | MIME type reported by the client (not verified server-side) |
| `getSize()`            | `int`    | File size in bytes                                          |
| `moveTo(string $path)` | `void`   | Move to destination; throws `\RuntimeException` on failure  |

---

## Controller Example

```php
class UserController extends \Merlin\Mvc\Controller
{
    public function createAction(): array
    {
        $request = $this->request();

        if (!$request->hasPost('email')) {
            return ['ok' => false, 'error' => 'email is required'];
        }

        $email = $request->post('email');
        User::create(['email' => $email]);

        return ['ok' => true];
    }

    public function apiAction(): array
    {
        $data = $this->request()->getJsonBody();
        // process $data ...
        return ['ok' => true];
    }
}
```

---

## Related

- [src/Http/Request.php](../src/Http/Request.php)
- [src/Http/UploadedFile.php](../src/Http/UploadedFile.php)
- [Controllers & Views](03-CONTROLLERS-VIEWS.md)
- [Validation](07-VALIDATION.md)
- [Security](09-SECURITY.md)
