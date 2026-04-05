# Controllers & Views

**Build your application logic and presentation** - Learn how to create controllers that handle requests, use dependency injection, work with the view engine, and render templates with layouts. Includes lifecycle hooks, middleware, and best practices.

Controllers coordinate request handling and return responses. View rendering is handled by `Merlin\Mvc\ViewEngine`.

---

## Controller Basics

Extend `Merlin\Mvc\Controller` and add public methods whose names end with `Action`. The `Dispatcher` resolves the controller and action from the matched route, injects dependencies, and invokes the action.

```php
<?php
namespace App\Controllers;

use Merlin\Mvc\Controller;

class UserController extends Controller
{
    public function indexAction(): string
    {
        return 'User index';
    }
}
```

---

## Dependency Injection

Action method parameters are resolved automatically by the `Dispatcher` in this priority order:

1. **Route parameter** — matched by parameter name (e.g. `{id}` → `$id`)
2. **DI / type-hint** — resolved from `AppContext` by type name (auto-wired if needed)
3. **Default value** — from the method signature
4. **Nullable** — injected as `null`

```php
use Merlin\Db\DatabaseManager;

class UserController extends Controller
{
    // $id comes from the route; $db is DI-resolved from AppContext
    public function viewAction(int $id, DatabaseManager $db): array
    {
        $user = User::find($id);
        return $user ? ['id' => $user->id, 'email' => $user->email] : [];
    }
}
```

Constructor parameters on the controller itself are also auto-wired via `AppContext::get()`.

---

## Available Controller Helpers

All helpers delegate to `AppContext` and are available anywhere inside the controller:

| Method             | Returns                         |
| ------------------ | ------------------------------- |
| `$this->context()` | `AppContext`                    |
| `$this->request()` | `Merlin\Http\Request`           |
| `$this->view()`    | `Merlin\Mvc\ViewEngine`         |
| `$this->session()` | `Merlin\Http\Session` or `null` |
| `$this->cookies()` | `Merlin\Http\Cookies`           |

---

## Returning Responses

The `Dispatcher` automatically converts controller return values into HTTP responses:

| Return type                  | Response produced                        |
| ---------------------------- | ---------------------------------------- |
| `Merlin\Http\Response`       | sent as-is                               |
| `array` / `JsonSerializable` | `200 application/json`                   |
| `string`                     | `200 text/html`                          |
| `int`                        | status-only response (e.g. `return 403`) |
| `null`                       | `204 No Content`                         |

```php
use Merlin\Http\Response;

class HealthController extends Controller
{
    public function pingAction(): array
    {
        return ['ok' => true];   // → 200 JSON
    }

    public function movedAction(): Response
    {
        return Response::redirect('/new-location');
    }

    public function adminOnlyAction(): int
    {
        return 403;   // → 403 status-only response
    }
}
```

---

## Lifecycle Hooks

The `Controller` base class provides two optional hooks that are called by the `Dispatcher` around every action invocation. Override them to add cross-cutting behavior such as access checks, logging, or response modification — without touching individual action methods.

### `beforeAction()`

Called **before** the action method. If it returns a `Response`, the action is skipped entirely and that response is sent instead. Returning `null` lets the action run normally.

```php
public function beforeAction(string $action = null, array $params = []): ?Response
{
    return null; // continue
}
```

### `afterAction()`

Called **after** the action method, inside a `finally` block — so it always fires even if the action throws an exception. If it returns a `Response`, that response replaces the one produced by the action.

```php
public function afterAction(string $action = null, array $params = []): ?Response
{
    return null; // keep the original response
}
```

- `$action` is the resolved PHP method name (e.g. `"editAction"`).
- `$params` contains the resolved arguments that were (or would have been) passed to the action.

### Example: require login before every action

```php
class AccountController extends Controller
{
    public function beforeAction(string $action = null, array $params = []): ?Response
    {
        if (!$this->session()?->get('user_id')) {
            return Response::redirect('/login');
        }
        return null;
    }

    public function dashboardAction(): string
    {
        return $this->view()->render('account/dashboard');
    }
}
```

### Example: add a response header after every action

```php
class ApiController extends Controller
{
    public function afterAction(string $action = null, array $params = []): ?Response
    {
        // returning null keeps the original response unchanged
        return null;
    }
}
```

> **Tip:** For concerns that should span many controllers (authentication, CORS, rate-limiting), prefer **middleware** over hooks. Hooks are best for per-controller teardown or lightweight checks that need controller context.

---

## Middleware

Middleware wraps the entire request pipeline around controller actions. The execution order is:

```
Global middleware → Route-group middleware → Controller middleware → Action middleware → beforeAction → action → afterAction
```

Each middleware implements `Merlin\Mvc\MiddlewareInterface`:

```php
interface MiddlewareInterface
{
    public function process(AppContext $context, callable $next): ?Response;
}
```

Return `null` (or `$next($context)`) to pass control to the next layer. Return a `Response` to short-circuit the rest of the pipeline.

### Writing a Middleware

```php
<?php
namespace App\Middleware;

use Merlin\AppContext;
use Merlin\Http\Response;
use Merlin\Mvc\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(AppContext $context, callable $next): ?Response
    {
        if (!$context->session()?->get('user_id')) {
            return Response::redirect('/login');
        }

        return $next($context);
    }
}
```

### Global Middleware

Registered on the `Dispatcher` — runs on every request before any controller or action-specific middleware:

```php
$dispatcher->addMiddleware(new SessionMiddleware());
$dispatcher->addMiddleware(new CorsMiddleware());
```

### Named Middleware Groups

Define groups on the `Dispatcher` and attach them inline with `Router::middleware()`:

```php
// bootstrap
$dispatcher->defineMiddlewareGroup('auth', [new AuthMiddleware()]);
$dispatcher->defineMiddlewareGroup('admin', [new AuthMiddleware(), new RoleMiddleware('admin')]);

// routing
$router->middleware('auth');
$router->add('GET', '/account', 'AccountController::indexAction');

$router->middleware('admin');
$router->add('GET',    '/admin/users',      'Admin\UserController::indexAction');
$router->add('DELETE', '/admin/users/{id}', 'Admin\UserController::deleteAction');
```

### Controller-Level Middleware

Declared as a protected property — runs for every action in the controller:

```php
class AdminController extends Controller
{
    protected array $middleware = [
        AuthMiddleware::class,                       // instantiated with no args
        [RoleMiddleware::class, ['admin']],           // instantiated with constructor args
    ];
}
```

### Action-Level Middleware

Declared per action name — runs only for that specific action, after controller middleware:

```php
class UserController extends Controller
{
    protected array $middleware = [AuthMiddleware::class];

    protected array $actionMiddleware = [
        'deleteAction' => [
            [RoleMiddleware::class, ['admin']],
        ],
        'exportAction' => [
            ThrottleMiddleware::class,
        ],
    ];

    public function deleteAction(int $id): int
    {
        User::find($id)?->delete();
        return 204;
    }
}
```

### Middleware Definitions

All three places (`$middleware`, `$actionMiddleware`, group arrays) accept the same definition formats:

| Format                                  | Behavior                                           |
| --------------------------------------- | -------------------------------------------------- |
| `MyMiddleware::class`                   | Instantiated with `new MyMiddleware()`             |
| `[MyMiddleware::class, [$arg1, $arg2]]` | Instantiated with `new MyMiddleware($arg1, $arg2)` |
| `new MyMiddleware()`                    | Used as-is (already an instance)                   |
| `fn($ctx, $next) => $next($ctx)`        | Closure, wrapped automatically                     |

---

## ViewEngine Basics

The `ViewEngine` API is shared by all engines. The default engine is `ClarityEngine`, which compiles `.clarity.html` templates with auto-escaping, template inheritance, and a filter pipeline. See [Clarity Engine](03b-CLARITY-ENGINE.md) for the full syntax reference.

Configure the view service in your bootstrap:

```php
use Merlin\AppContext;

$view = AppContext::instance()->view();
$view->setViewPath(__DIR__ . '/../views');
$view->setLayout('layouts/main'); // wraps every render() call
```

Render inside a controller:

```php
class PageController extends Controller
{
    public function homeAction(): string
    {
        return $this->view()->render('home/index', [
            'title'   => 'Home',
            'message' => 'Welcome',
        ]);
    }
}
```

The rendered view is injected into the layout. For partials (no layout), use `renderPartial()`:

```php
$html = $this->view()->renderPartial('partials/header', ['user' => $user]);
```

---

## View Name Resolution

The `ViewEngine` resolves view names to filesystem paths using the following rules:

**Relative names** — dot-notation is converted to directory separators:

- `users.index` → `users/index.php`
- `admin.users.edit` → `admin/users/edit.php`

**Namespaced views** — `namespace::view.name` resolves to the namespace root:

- `admin::dashboard.index` → `{admin-path}/dashboard/index.php`

**Dot-prefixed paths** — treated as literal relative paths:

- `./partials/header` → `./partials/header.php`

**Absolute paths** — used as-is:

- `/var/www/views/custom.php`

---

## View Variables

Variables can be set globally on the `ViewEngine` (available in every view) or passed per render:

```php
// Global — available in all views rendered through this engine
$this->view()->setVar('appName', 'MyApp');
$this->view()->setVars(['locale' => 'en', 'user' => $currentUser]);

// Per render
$html = $this->view()->render('user/index', [
    'users' => User::findAll(),
    'title' => 'All Users',
]);
```

---

## View Namespaces

Namespaces let you organize views from different parts of the application under distinct roots:

```php
// bootstrap
$view->addNamespace('admin', __DIR__ . '/../views/admin');
$view->addNamespace('mail',  __DIR__ . '/../views/email');

// usage
echo $this->view()->render('admin::users.index');
echo $this->view()->renderPartial('mail::welcome', ['user' => $user]);
```

---

## Validating Input

Validate and coerce request data with `Merlin\Validation\Validator` before using it in your controller logic or passing it to a model.

```php
use Merlin\Validation\Validator;
use Merlin\Validation\ValidationException;

class UserController extends Controller
{
    public function createAction(): array
    {
        $v = new Validator($this->request()->post());
        $v->field('name')->string()->min(2)->max(100);
        $v->field('email')->email()->max(255);
        $v->field('role')->optional()->in(['admin', 'editor', 'viewer']);

        if ($v->fails()) {
            return ['success' => false, 'errors' => $v->errors()];
        }

        $user = User::create($v->validated());
        return ['success' => true, 'id' => $user->id];
    }
}
```

See [Validation](07-VALIDATION.md) for the complete rule reference.

---

## Related

- [Clarity Engine](03b-CLARITY-ENGINE.md)
- [MVC Routing](02-MVC-ROUTING.md)
- [HTTP Request](06-HTTP-REQUEST.md)
- [API Reference](api/README.md)
