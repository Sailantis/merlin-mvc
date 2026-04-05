# MVC Routing

**Map URLs to controllers** - Master Merlin's routing system to define URL patterns, handle parameters, group routes, and apply middleware. Learn how to create RESTful routes, named routes, and custom parameter validation.

`Merlin\Mvc\Router` matches URI + HTTP method to controller/action metadata.
`Merlin\Mvc\Dispatcher` executes that route and returns a `Response`.

## Basic Usage

```php
use Merlin\AppContext;
use Merlin\Mvc\Dispatcher;
use Merlin\Mvc\Router;

$ctx = AppContext::instance();

$router = $ctx->router();
$router->add('GET', '/', 'IndexController::indexAction');
$router->add('GET', '/users/{id:int}', 'UserController::viewAction');

// Configure dispatcher
$dispatcher = new Dispatcher();
$dispatcher->setBaseNamespace('\\App\\Controllers');

$route = $router->match('/users/42', 'GET');
if ($route !== null) {
    $response = $dispatcher->dispatch($route);
    $response->send();
}
```

## Route Patterns

The Router supports several pattern styles to match different URL structures. You can use static paths for exact matches, named parameters to capture URL segments, and type constraints to validate parameter formats.

### Static

Static routes match exact paths and are the fastest to resolve:

```php
$router->add('GET', '/about', 'PageController::aboutAction');
```

### Named Parameters

Capture dynamic segments from the URL as parameters passed to your controller action:

```php
$router->add('GET', '/blog/{slug}', 'BlogController::showAction');
```

### Typed Parameters

Add type constraints to validate parameters automatically. This helps prevent invalid data from reaching your controllers and makes routes more self-documenting.

Built-in types:

| Type    | Accepts                            |
| ------- | ---------------------------------- |
| `int`   | Digits only (`ctype_digit`)        |
| `alpha` | Letters only (`ctype_alpha`)       |
| `alnum` | Letters and digits (`ctype_alnum`) |
| `uuid`  | 36-char UUID with hyphens          |
| (none)  | Any single segment (no validation) |

```php
$router->add('GET', '/users/{id:int}', 'UserController::viewAction');
$router->add('GET', '/tags/{name:alpha}', 'TagController::showAction');
$router->add('GET', '/items/{slug}', 'ItemController::showAction'); // any single segment
```

> **Note:** `{slug}` (no type) matches **one** URL segment with any value. `{files:*}` (explicit `*` type) is a **wildcard** that captures all remaining segments as an array — see [Wildcard Parameters](#wildcard-parameters) below.

#### Regex Type

Use the `regex` type to match URL segments against a custom regular expression pattern. This is useful when the built-in types don't meet your needs. The pattern should be a valid PCRE regex without delimiters.

```php
// Match ISO date format
$router->add('GET', '/articles/{date:regex(\d{4}-\d{2}-\d{2})}', 'ArticleController::showAction');

// Match namespaced API routes (e.g., v1 and v2)
$router->add('GET', '/api/{namespace:regex(v[1-2])}/users', 'ApiController::usersAction');
```

The regex pattern is matched using PCRE's `preg_match()` function. Ensure your pattern is specific enough to avoid unintended matches, and remember that the pattern is matched against individual URL segments only, not across segment boundaries.

### Optional Parameters

Append `?` to a parameter name to make it optional. The segment is accepted when present and valid, but the route still matches when it is absent.

```php
// /users or /users/42 both match
$router->add('GET', '/users/{id?:int}', 'UserController::listOrViewAction');

// /archive or /archive/2026 or /archive/2026-01-15 all match
$router->add('GET', '/archive/{date?:regex(\d{4}(-\d{2}-\d{2})?)}', 'ArchiveController::indexAction');
```

In your action, declare the parameter as nullable or give it a default value:

```php
public function listOrViewAction(?int $id = null): Response
{
    if ($id === null) {
        // list all
    } else {
        // view single
    }
}
```

### Routing Variables

Certain parameter names have special meaning to the Dispatcher and control how controllers and actions are resolved:

```php
$router->add('GET', '/{controller}/{action}');
$router->add('GET', '/api/{namespace}/{controller}/{action}');
```

Routing variables recognized by the Dispatcher:

- `{namespace}` - Appends to the base namespace for controller resolution
- `{controller}` - Specifies the controller name (converted to PascalCase + 'Controller')
- `{action}` - Specifies the action method name (converted to camelCase + 'Action')

Example: `/api/admin/user/view` with pattern `/api/{namespace}/{controller}/{action}` yields:

- `namespace` → `Admin` (appended to base namespace)
- `controller` → `UserController`
- `action` → `viewAction`

**Note:** When you specify a handler (third parameter to `add()`), it overrides the routing variables. This lets you use parameters named `controller`, `action`, etc. for other purposes:

```php
// Uses routing variables to resolve controller/action dynamically
$router->add('GET', '/{controller}/{action}');

// Handler overrides - 'controller' becomes a regular parameter
$router->add('GET', '/admin/{controller}', 'AdminController::manageAction');
```

## HTTP Methods

Routes can be restricted to specific HTTP methods for proper RESTful API design. You can specify a single method, an array of methods, or `*` or null to match all common methods (GET, POST, PUT, DELETE, PATCH, OPTIONS).

```php
$router->add('GET', '/users', 'UserController::listAction');
$router->add(['PUT', 'PATCH'], '/users/{id:int}', 'UserController::updateAction');
$router->add('*', '/health', 'HealthController::statusAction');
```

## Named Routes and URL Generation

Named routes let you generate URLs programmatically without hardcoding paths. This makes refactoring routes easier and keeps your codebase maintainable.

```php
$router->add('GET', '/users/{id:int}', 'UserController::viewAction')
    ->setName('user.view');

$url = $router->urlFor('user.view', ['id' => 42], ['tab' => 'profile']);
// /users/42?tab=profile
```

## Custom Parameter Types

Define your own validation rules for route parameters. This is useful for application-specific formats like slugs, SKUs, or reference codes.

```php
$router->addType('slug', fn(string $v) => preg_match('/^[a-z0-9-]+$/', $v));
$router->add('GET', '/posts/{slug:slug}', 'PostController::showAction');
```

## Route Priority

When multiple routes could match the same URL, the Router picks the most specific one automatically — no manual ordering is required. Specificity is scored per segment:

| Segment kind                                | Score |
| ------------------------------------------- | ----- |
| Static literal                              | 3     |
| Typed / regex parameter                     | 2     |
| Untyped or wildcard (`{name}` / `{name:*}`) | 1     |

The route with the highest total score wins.

```php
// /users/settings matches the static route, not the parameter route
$router->add('GET', '/users/{id:int}', 'UserController::viewAction'); // score 5 (3+2)
$router->add('GET', '/users/settings', 'UserController::settingsAction'); // score 6 (3+3) ← wins
```

## Prefix, Namespace, Controller, and Middleware Groups

Use the grouping methods in two ways:

- Callback form: the group is temporary and the router restores the previous state when the callback returns.
- Inline form: omit the callback and the group stays active for subsequent routes in the current routing setup.

```php
// Inline middleware applies to later routes
$router->middleware('auth');
$router->add('GET', '/account', 'AccountController::indexAction');

// Inline prefix applies to later routes
$router->prefix('/admin');
$router->add('GET', '/dashboard', 'AdminController::indexAction');

// Inline namespace is prepended to later handlers
$router->namespace('Admin');
$router->add('GET', '/users', 'UserController::listAction');

// Inline controller supplies the controller part for later handlers
$router->controller('UserController');
$router->add('GET', '/users', '::listAction');

// Scoped callback forms still work when you want temporary grouping
$router->middleware(['auth', 'admin'], function (Router $r) {
    $r->add('DELETE', '/users/{id:int}', 'UserController::deleteAction');
});

$router->prefix('/api', function (Router $r) {
    $r->namespace('Api');
    $r->middleware('auth');
    $r->add('GET', '/orders', 'OrderController::listAction');
});
```

Inline groups can be combined, and callback-scoped groups automatically restore the previous stack when they finish.

## Dispatcher Configuration

The Dispatcher handles controller resolution and default values:

```php
$dispatcher = new Dispatcher();
$dispatcher->setBaseNamespace('\\App\\Controllers'); // Default: '\\App\\Controllers'
$dispatcher->setDefaultController('IndexController'); // Default: 'IndexController'
$dispatcher->setDefaultAction('indexAction');         // Default: 'indexAction'
```

> **Note:** The base namespace should start with a leading backslash (`\`). Relative namespace overrides from the Router's `namespace()` groups are appended to it automatically.

## Route Information in AppContext

When the Dispatcher processes a route, it stores the routing information in `AppContext->route` as a `ResolvedRoute` object. This makes the current route accessible throughout your application:

```php
// In any controller, middleware, or service:
$route = AppContext::instance()->route();

// Access route details:
$controller = $route->controller; // Full controller class name
$action = $route->action; // Action method name
$namespace = $route->namespace; // Resolved namespace
$vars = $route->vars; // All route variables
$params = $route->params; // Resolved arguments passed to the action
$groups = $route->groups; // Middleware groups
$override = $route->override; // Handler overrides
```

## Important Notes

- Router focuses purely on pattern matching - no namespace or defaults
- Dispatcher handles controller resolution, defaults, and namespace logic
- Use `Router::match(...)` then `Dispatcher::dispatch(...)`
- Route info is stored in `AppContext->route` during dispatch

## Dispatcher Argument Resolution

The Dispatcher resolves action method parameters in the following order for each parameter:

1. **By name from route variables** – if a route variable matches the parameter name, its value is used and cast to the declared type when possible.
2. **By type from DI (AppContext)** – if the parameter has a class or interface type hint that is registered in `AppContext` (or is an instantiable class), it is auto-wired.
3. **Default value** – the value declared in the method signature.
4. **Nullable** – injected as `null`.

If none of the above apply, a `RuntimeException` is thrown.

### Wildcard Parameters

A wildcard segment (`{segments:*}`) captures all remaining path segments as an `array`. To receive this value in an action, declare the parameter either as variadic or typed as `array`:

```php
$router->add('GET', '/files/{params:*}', 'FileController::readAction');

class FileController extends Controller
{
    // Variadic: each segment becomes a separate argument
    public function readAction(string ...$params): Response
    {
        $path = implode('/', $params); // e.g. 'images/2026/photo.jpg'
        // ...
    }

    // Alternative: receive all segments as a plain array
    // public function readAction(array $params): Response { ... }
}
```

### DI Injection Example

Parameters that can't be matched by name fall through to DI resolution. Here is a full cycle example:

#### 1. Define a Service

```php
// src/Services/Greeter.php
namespace App\Services;

class Greeter
{
    public function greet(string $name): string
    {
        return "Hello, $name!";
    }
}
```

#### 2. Register the Service in AppContext

```php
use Merlin\AppContext;
use App\Services\Greeter;

$ctx = AppContext::instance();
// Register Greeter as a lazy service. It will be instantiated once, on first request.
$ctx->set(Greeter::class, fn() => new Greeter());
// Or register an already-built singleton instance.
// $ctx->set(Greeter::class, new Greeter());
```

#### 3. Inject and Use in a Controller Action

```php
use App\Services\Greeter;

class WelcomeController extends Controller
{
    // $name is matched by route; $greeter is injected from AppContext by type
    public function helloAction(string $name, Greeter $greeter): array
    {
        return [
            'message' => $greeter->greet($name)
        ];
    }
}
```

#### 4. Route Example

```php
$router->add('GET', '/hello/{name}', 'WelcomeController::helloAction');
// GET /hello/World → {"message":"Hello, World!"}
```

## See Also

- [Controllers & Views](03-CONTROLLERS-VIEWS.md)
- [API Reference](api/README.md)
