# Clarity Template Engine

**A sandboxed, compiled template engine for Merlin** – Clarity compiles `.clarity.html` files into PHP classes that are cached on disk. Templates can only access variables passed to `render()` and registered filters; arbitrary PHP code is intentionally disallowed.

---

## Setup

`ClarityEngine` is the default view engine. Configure it in your bootstrap:

```php
use Merlin\AppContext;

$ctx = AppContext::instance();

$ctx->view()
    ->setViewPath(__DIR__ . '/../views')  // optional, defaults to "views" in the project root
    ->setLayout('layouts/main');          // optional default layout
```

To use plain-PHP templates instead, swap to `NativeEngine`:

```php
use Merlin\Mvc\Engines\NativeEngine;

$ctx->setView(new NativeEngine());
$ctx->view()->setViewPath(__DIR__ . '/../views');
```

### Configuration

| Method                                   | Description                                                              |
| ---------------------------------------- | ------------------------------------------------------------------------ |
| `setViewPath(string $path)`              | Base directory where templates are found                                 |
| `setLayout(?string $layout)`             | Default layout template (`null` disables the layout)                     |
| `setExtension(string $ext)`              | Override the file extension (default: `.clarity.html`)                   |
| `setCachePath(string $path)`             | Directory for compiled PHP files (default: `sys_get_temp_dir()/clarity`) |
| `getCachePath()`                         | Return the current cache path                                            |
| `flushCache()`                           | Delete all compiled files – useful during development                    |
| `addFilter(string $name, callable $fn)`  | Register a custom filter                                                 |
| `addNamespace(string $ns, string $path)` | Register a named directory for template resolution                       |

---

## Template Syntax at a Glance

```
{{ expression }}          Output a value (auto-escaped)
{{ expression |> raw }}   Output raw HTML (no escaping)
{% directive %}           Control flow, assignment, includes, inheritance
```

---

## Output Tags

Enclose any Clarity expression in double curly braces to print it:

```html
<p>Hello, {{ user.name }}!</p>
```

**Auto-escaping** is always applied: every output tag calls `htmlspecialchars()` automatically. To print raw HTML, pipe through `raw`:

```html
{# trusted HTML stored in a variable #}
<div>{{ body |> raw }}</div>
```

---

## Expressions

### Variable Access

Variables are accessed via dot notation or bracket notation. Direct PHP variables (`$name`) are forbidden.

```
user.name                 → $vars['user']['name']
items[0]                  → $vars['items'][0]
items[index]              → $vars['items'][$vars['index']]
a.b[c.d].e                → $vars['a']['b'][$vars['c']['d']]['e']
```

### Operators

| Clarity                          | PHP equivalent | Notes                |
| -------------------------------- | -------------- | -------------------- |
| `and`                            | `&&`           |                      |
| `or`                             | `\|\|`         |                      |
| `not`                            | `!`            |                      |
| `~`                              | `.`            | String concatenation |
| `==`, `!=`, `<`, `>`, `<=`, `>=` | same           |                      |
| `+`, `-`, `*`, `/`, `%`          | same           |                      |
| `true`, `false`, `null`          | same           |                      |

```html
{% if user.active and user.role == 'admin' %}
<span>Admin</span>
{% endif %}

<p>{{ firstName ~ ' ' ~ lastName }}</p>
```

Function calls (e.g. `strtoupper(name)`) are **not allowed** in expressions. Use the filter pipeline instead.

---

## Filter Pipeline (`|>`)

Filters transform a value before it is output. Chain multiple filters with `|>`:

```html
{{ user.name |> upper }} {{ price |> number(2) }} {{ createdAt |> date('d.m.Y')
}} {{ description |> trim |> upper }}
```

Filters with arguments use parentheses after the filter name:

```html
{{ amount |> number(0) }} {# 0 decimal places #} {{ timestamp |> date('H:i') }}
{# format as time #}
```

### Built-in Filters

| Filter         | Signature                   | Description                                     |
| -------------- | --------------------------- | ----------------------------------------------- |
| `escape` / `e` | `(value)`                   | `htmlspecialchars(UTF-8, ENT_QUOTES)`           |
| `raw`          | `(value)`                   | Pass through unchanged; disables auto-escaping  |
| `trim`         | `(value)`                   | Remove leading/trailing whitespace              |
| `upper`        | `(value)`                   | `strtoupper`                                    |
| `lower`        | `(value)`                   | `strtolower`                                    |
| `length`       | `(value)`                   | `strlen` for strings, `count` for arrays        |
| `number`       | `(value, decimals = 2)`     | `number_format`                                 |
| `date`         | `(value, format = 'Y-m-d')` | Formats a Unix timestamp or `DateTimeInterface` |
| `json`         | `(value)`                   | `json_encode`                                   |

### Custom Filters

Register additional filters in your bootstrap:

```php
$ctx->view()->addFilter('currency', fn($v, string $sym = '€') =>
    number_format($v, 2) . ' ' . $sym
);

$ctx->view()->addFilter('excerpt', fn($v, int $len = 100) =>
    mb_strlen($v) > $len ? mb_substr($v, 0, $len) . '…' : $v
);
```

Use them in templates:

```html
{{ product.price |> currency }} {{ product.price |> currency('$') }} {{
article.body |> excerpt(150) }}
```

---

## Control Flow

### If / Elseif / Else

```html
{% if stock > 0 %}
<button>Add to cart</button>
{% elseif stock == 0 %}
<span>Out of stock</span>
{% else %}
<span>Unavailable</span>
{% endif %}
```

### For Loops

**Iterate over a list:**

```html
<ul>
  {% for item in items %}
  <li>{{ item.name }}</li>
  {% endfor %}
</ul>
```

**Exclusive range** (`..`) – last value is not included:

```html
{% for i in 1..10 %} {{ i }} {% endfor %} {# prints 1 2 3 4 5 6 7 8 9 #}
```

**Inclusive range** (`...`) – last value is included:

```html
{% for i in 1...10 %} {{ i }} {% endfor %} {# prints 1 2 3 4 5 6 7 8 9 10 #}
```

**With a step:**

```html
{% for i in 0...100 step 10 %} {{ i }} {% endfor %} {# prints 0 10 20 30 40 50
60 70 80 90 100 #}
```

Ranges can use variables:

```html
{% for i in start...end step stride %} {{ i }} {% endfor %}
```

### Variable Assignment

```html
{% set total = items.length %} {% set label = user.firstName ~ ' ' ~
user.lastName %}

<p>{{ total }} items for {{ label }}</p>
```

---

## Template Inheritance

Clarity implements block-based template inheritance. A child template extends a parent layout and overrides named blocks.

**Parent layout** (`layouts/main.clarity.html`):

```html
<!DOCTYPE html>
<html>
  <head>
    <title>{% block title %}My App{% endblock %}</title>
    {% block head %}{% endblock %}
  </head>
  <body>
    {% block body %}{% endblock %} {% block footer %}
    <footer>&copy; My App</footer>
    {% endblock %}
  </body>
</html>
```

**Child template** (`pages/home.clarity.html`):

```html
{% extends "layouts/main" %} {% block title %}Home – My App{% endblock %} {%
block body %}
<h1>Welcome, {{ user.name }}!</h1>
{% endblock %}
```

- Blocks not overridden in the child retain the parent's default content.
- Inheritance is resolved at **compile time** – no runtime overhead.
- Nesting is supported: a child layout may itself extend another parent.

---

## Includes

Embed another template inline using `{% include %}`. The included file shares the current variable scope.

```html
{% include "partials/nav" %} {% include "partials/user_card" %}
```

Included files are compiled and inlined at compile time. They do not create a separate render call.

### Named Namespaces

Register a named directory and reference templates with the `namespace::path` syntax:

```php
$ctx->view()->addNamespace('admin', __DIR__ . '/../views/admin');
```

```html
{% include "admin::partials/sidebar" %} {% extends "admin::layouts/main" %}
```

Dots and slashes are interchangeable as path separators:

```html
{% include "admin::partials.sidebar" %} {# same as above #}
```

---

## Layouts via Controller

Use the view engine helpers in your controller as usual – `ClarityEngine` honors the same `ViewEngine` API:

```php
class ArticleController extends Controller
{
    public function showAction(int $id): string
    {
        $article = Article::findOrFail($id);

        // No layout for this response
        $this->view()->setLayout(null);
        return $this->view()->render('articles/show', [
            'article' => $article,
        ]);
    }
}
```

The `content` variable is automatically injected into the layout template when a layout is active (identical to `NativeEngine` behaviour).

---

## Caching

Compiled PHP classes are written to the cache directory and served from there on subsequent requests. OPcache picks them up transparently, so warm-path rendering requires no file I/O.

Cache files are **automatically invalidated** when any source file they depend on (the template itself, extended layouts, included partials) is modified.

```php
// Custom cache location
$ctx->view()->setCachePath('/var/cache/clarity');

// Flush during development when template changes are not being picked up
$ctx->view()->flushCache();
```

> **Tip:** In production, point the cache to a persistent directory outside `/tmp` and ensure the web server user has write access.

---

## Security Sandbox

Clarity templates have **no access to PHP**:

- Direct PHP variables (`$name`) are rejected at compile time.
- Function calls (`strtoupper(x)`) are rejected at compile time – use the filter pipeline.
- Statement delimiters (`;`), backticks, heredocs, and PHP open/close tags are disallowed in expressions.
- Objects passed to `render()` are automatically cast to arrays (via `JsonSerializable`, `toArray()`, or `get_object_vars()`), preventing method calls from inside templates.

---

## Choosing Between ClarityEngine and NativeEngine

|                       | ClarityEngine _(default)_                                    | NativeEngine                             |
| --------------------- | ------------------------------------------------------------ | ---------------------------------------- |
| Template syntax       | Clarity DSL (`{{ }}`, `{% %}`)                               | Plain PHP (`<?= ?>`, `<?php ?>`)         |
| Sandboxed             | Yes                                                          | No                                       |
| Auto-escaping         | Always on by default                                         | Manual                                   |
| Compilation & caching | Yes                                                          | Native                                   |
| Template inheritance  | `{% extends %}` / `{% block %}`                              | Not built-in                             |
| Filter pipeline       | Built-in                                                     | Not built-in                             |
| Suitable for          | User-facing views, team projects, untrusted template authors | Full PHP control, existing PHP templates |

---

## Full Bootstrap Example

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Merlin\AppContext;
use Merlin\Db\Database;
use Merlin\Http\Response;
use Merlin\Http\SessionMiddleware;
use Merlin\Mvc\Dispatcher;
use Merlin\Mvc\Router;

$ctx = AppContext::instance();

// Database
$ctx->dbManager()->set('default', fn() => new Database(
    'mysql:host=localhost;dbname=myapp', 'user', 'pass'
));

// ClarityEngine is the default – just configure it
$ctx->view()
    ->setViewPath(__DIR__ . '/../views')
    ->setLayout('layouts/main')
    ->setCachePath('/var/cache/clarity');

// Custom filters
$ctx->view()->addFilter('currency', fn($v) => '€ ' . number_format($v, 2));

// Routing & dispatching
$router = $ctx->router();
$router->add('GET', '/', 'IndexController::indexAction');

$dispatcher = new Dispatcher();
$dispatcher->setBaseNamespace('\\App\\Controllers');
$dispatcher->addMiddleware(new SessionMiddleware());

$route = $router->match(
    $ctx->request()->getPath(),
    $ctx->request()->getMethod()
);

if ($route === null) {
    Response::status(404)->send();
} else {
    $dispatcher->dispatch($route)->send();
}
```
