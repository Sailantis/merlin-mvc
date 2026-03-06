# Merlin Framework – Copilot Instructions

Merlin is a lightweight PHP MVC framework (PHP >= 8.1). Use these instructions when generating code or answering questions about this project.

---

## Project Layout

```
src/
  AppContext.php          # Service container + singleton + ResolvedRoute
  Crypt.php               # Static authenticated encryption (Sodium / OpenSSL)
  Exception.php
  Cli/                    # Console, Task, Exceptions/
  Db/                     # Database, Query, Condition, ResultSet, Sql, SqlCase,
  |                       # DatabaseManager, Paginator, Exceptions/
  Http/                   # Request, Response, Session, SessionMiddleware,
  |                       # Cookies, Cookie, UploadedFile
  Mvc/                    # Router, Dispatcher, Controller, Model, ModelMapping,
                          # ViewEngine, MiddlewareInterface, Exceptions/
```

---

## AppContext

`Merlin\AppContext` is a singleton service container and the central runtime object.

```php
$ctx = AppContext::instance();          // get (or create) the singleton
AppContext::setInstance($customCtx);    // replace singleton (useful in tests)
```

### Registering / Accessing Services

```php
$ctx->set(MyService::class, new MyService());   // register
$ctx->has(MyService::class);                    // check
$ctx->get(MyService::class);                    // resolve (auto-wires if class exists)
$ctx->tryGet(MyService::class);                 // resolve or null
$ctx->getOrNull(MyService::class);              // already registered only, or null
```

Auto-wiring: if `get($id)` receives a class name that is not registered but exists, `AppContext` will instantiate it via reflection, recursively resolving constructor parameters from the container.

### Built-in Service Accessors

| Method                       | Returns                      |
| ---------------------------- | ---------------------------- |
| `$ctx->request()`            | `Merlin\Http\Request`        |
| `$ctx->view()`               | `Merlin\Mvc\ViewEngine`      |
| `$ctx->session()`            | `Merlin\Http\Session\|null`  |
| `$ctx->setSession($session)` | sets the active session      |
| `$ctx->cookies()`            | `Merlin\Http\Cookies`        |
| `$ctx->dbManager()`          | `Merlin\Db\DatabaseManager`  |
| `$ctx->route()`              | `Merlin\ResolvedRoute\|null` |
| `$ctx->setRoute($route)`     | sets the resolved route      |

### Configuring the View Engine

The default engine is `ClarityEngine` (Clarity DSL, `.clarity.html` files). Use `setView()` to switch to `NativeEngine` (plain PHP) if needed.

```php
$ctx->view()->setViewPath(__DIR__ . '/../views');      // required
$ctx->view()->setLayout('layouts/main');               // optional default layout
$ctx->view()->setCachePath('/var/cache/clarity');      // optional, default: sys_temp/clarity
$ctx->view()->addFilter('upper', fn($v) => strtoupper($v)); // custom filter
$ctx->view()->addNamespace('admin', __DIR__ . '/../views/admin');

// Switch to plain-PHP templates instead
use Merlin\Mvc\Engines\NativeEngine;
$ctx->setView(new NativeEngine());
$ctx->view()->setViewPath(__DIR__ . '/../views');
$ctx->view()->setExtension('php');   // default for NativeEngine
```

---

## Database – DatabaseManager & Roles

`Merlin\Db\DatabaseManager` manages named database connections called **roles**. The first role registered automatically becomes the default.

```php
$mgr = $ctx->dbManager();

// Register connections
$mgr->set('write', new Database('mysql:host=primary;dbname=app', 'rw', 'secret'));
$mgr->set('read',  fn() => new Database('mysql:host=replica;dbname=app', 'ro', 'secret'));

// Override the default role
$mgr->setDefault('write');

// Retrieve
$mgr->get('read');          // specific role (throws if missing)
$mgr->getOrDefault('read'); // specific role or fallback to default
$mgr->getDefault();         // default role
$mgr->has('analytics');     // check existence
```

Factories are lazy: the callable is invoked only on first use; the resulting `Database` instance is cached.

---

## Models & DB Roles

`Merlin\Mvc\Model` reads from the `read` role and writes to the `write` role by default. These names are looked up via `DatabaseManager::getOrDefault()` (falls back to the default if the specific role is not configured).

### Override per Model Class

```php
// Both read and write to the same role
User::setDefaultRole('analytics');

// Fine-grained control
User::setDefaultReadRole('replica');
User::setDefaultWriteRole('primary');

// Reset to base default (affects all models unless overridden)
Model::setDefaultRole('default');
```

### Single-connection Setup

If you only have one database, just register it under any name – all models will fall through to the default:

```php
$ctx->dbManager()->set('default', new Database('mysql:host=localhost;dbname=app', 'user', 'pass'));
```

### Model Static Query Methods

```php
User::find($id);                          // ?static by primary key
User::findOrFail($id);                    // static or throws
User::findOne(['email' => $email]);       // ?static matching conditions
User::findAll(['status' => 'active']);    // ResultSet
User::exists(['email' => $email]);        // bool
User::count(['status' => 'active']);      // int (row count)
User::create(['email' => 'a@b.com']);     // insert + return model
User::forceCreate(['email' => 'a@b.com']);// insert ignoring fillable guard
User::firstOrCreate(['email' => $e], ['name' => $n]);
User::updateOrCreate(['email' => $e], ['name' => $n]);
User::query(?string $alias);              // returns Query builder for the model
```

### Model Instance Methods

```php
$user->save();    // INSERT if new, UPDATE if loaded; returns bool
$user->insert();  // always INSERT
$user->update();  // always UPDATE
$user->delete();  // DELETE this record

// State tracking (snapshot / detect drift)
$user->saveState();     // snapshot current field values
$user->loadState();     // restore to snapshot
$user->getState();      // returns snapshot or null
$user->hasChanged();    // true if fields differ from snapshot

// Connection access
$user->readConnection();   // Database (read role)
$user->writeConnection();  // Database (write role)
```

Model has **no `toArray()` method**. Use direct property access or build your own array from model fields.

---

## MiddlewareInterface

`Merlin\Mvc\MiddlewareInterface` must be implemented by all middleware classes:

```php
interface MiddlewareInterface
{
    public function process(AppContext $context, callable $next): ?Response;
}
```

Return `null` to continue the chain (calls `$next($context)`), or return a `Response` to short-circuit.

### SessionMiddleware

`Merlin\Http\SessionMiddleware` implements `MiddlewareInterface` and activates PHP session handling:

```php
use Merlin\Http\SessionMiddleware;

$dispatcher->addMiddleware(new SessionMiddleware());
```

After this middleware runs, `AppContext::instance()->session()` returns a `Session` instance.

---

## Dispatcher & DI

`Merlin\Mvc\Dispatcher` resolves and invokes controllers. It is instantiated **without arguments** and obtains `AppContext` internally via `AppContext::instance()`.

```php
$dispatcher = new Dispatcher();
$dispatcher->setBaseNamespace('\\App\\Controllers');
$dispatcher->setDefaultController('IndexController');
$dispatcher->setDefaultAction('indexAction');

$response = $dispatcher->dispatch($routeInfo);
```

### Middleware Pipeline

```php
// Global middleware – runs on every request
$dispatcher->addMiddleware(new SessionMiddleware());
$dispatcher->addMiddleware(new ExceptionCatcherMiddleware());

// Named middleware groups – referenced from Router::middleware() or controllers
$dispatcher->defineMiddlewareGroup('auth', [new AuthMiddleware()]);
$dispatcher->defineMiddlewareGroup('admin', [new AuthMiddleware(), new RoleMiddleware('admin')]);

// Custom controller factory (replaces DI resolution)
$dispatcher->setControllerFactory(function (string $class) use ($container) {
    return $container->make($class);
});
```

### Controller Instantiation via DI

Controllers are resolved through `AppContext::get($controllerClass)`. This means:

- Controllers with typed constructor parameters receive their dependencies via auto-wiring.
- Any registered service, including the `DatabaseManager` or custom services, can be injected.

### Action Parameter Injection

Action method parameters are resolved in this order:

1. **Route parameters** – matched by parameter name.
2. **DI (type-hint)** – resolved from `AppContext` by type name.
3. **Default value** – from the method signature.
4. **Nullable** – injected as `null`.

```php
class UserController extends Controller
{
    // $id comes from the route; $db is DI-resolved
    public function viewAction(int $id, DatabaseManager $db): array
    {
        $user = User::find($id);
        return $user ? ['id' => $user->id, 'email' => $user->email] : [];
    }
}
```

---

## Controller

`Merlin\Mvc\Controller` provides helpers and lifecycle hooks. All helper methods delegate to `AppContext`.

```php
class MyController extends Controller
{
    // Lifecycle hooks – return Response to short-circuit the action
    public function beforeAction(string $action = null, array $params = []): ?Response { ... }
    public function afterAction(string $action = null, array $params = []): ?Response { ... }

    // Convenience accessors
    public function context(): AppContext  { ... }
    public function request(): Request    { ... }
    public function view(): ViewEngine    { ... }
    public function session(): ?Session   { ... }
    public function cookies(): Cookies    { ... }
}
```

### Controller-Level Middleware

```php
class AdminController extends Controller
{
    // Runs for every action in this controller
    protected array $middleware = [AuthMiddleware::class];

    // Runs only for the named action
    protected array $actionMiddleware = [
        'deleteAction' => [[RoleMiddleware::class, ['admin']]],
    ];
}
```

---

## Router

```php
use Merlin\AppContext;
use Merlin\Mvc\Router;

$ctx = AppContext::instance();

$router = $ctx->router();
$router->add('GET', '/', 'IndexController::indexAction');
$router->add('GET', '/users/{id:int}', 'UserController::viewAction');

$routeInfo = $router->match($path, $method); // null if no match
```

### Named Routes and URL Generation

```php
$router->add('GET', '/users/{id:int}', 'UserController::viewAction')
       ->setName('user.view');

$router->hasNamedRoute('user.view');           // true
$router->urlFor('user.view', ['id' => 5]);    // '/users/5'
$router->urlFor('user.view', ['id' => 5], ['tab' => 'profile']); // '/users/5?tab=profile'
```

### Route Groups and Middleware

```php
// Prefix group
$router->prefix('/admin', function (Router $r) {
    $r->add('GET', '/dashboard', 'Admin\DashboardController::indexAction');
    $r->add('GET', '/users',     'Admin\UserController::indexAction');
});

// Namespace group – prepended to handler strings inside the callback
$router->namespace('Admin', function (Router $r) {
    $r->add('GET', '/dashboard', 'DashboardController::indexAction');
    $r->add('GET', '/users',     'UserController::indexAction');
});

// Controller group – all routes inside share the same controller
$router->controller('UserController', function (Router $r) {
    $r->add('GET',  '/users',      '::listAction');
    $r->add('POST', '/users',      '::createAction');
    $r->add('GET',  '/users/{id}', '::viewAction');
});

// Middleware group (name must match a group defined in Dispatcher)
$router->middleware('auth', function (Router $r) {
    $r->add('GET', '/account', 'AccountController::indexAction');
});
```

### Custom Parameter Types

```php
$router->addType('uuid', fn($v) => preg_match('/^[0-9a-f\-]{36}$/i', $v) === 1);
$router->add('GET', '/items/{id:uuid}', 'ItemController::viewAction');
```

### HTTP Method Shorthand

`add()` accepts `null` (any method), a string (`'GET'`), or an array (`['GET', 'POST']`).

---

## ViewEngine

The default engine is `ClarityEngine`. Templates use the Clarity DSL (`{{ }}`, `{% %}`) and are auto-compiled and cached. See [docs/03b-CLARITY-ENGINE.md](../docs/03b-CLARITY-ENGINE.md) for the full syntax reference.

```php
$view = $ctx->view();  // returns ClarityEngine by default
$view->setViewPath(__DIR__ . '/../views');  // base directory for views
$view->setLayout('layouts/main');          // default wrapping layout, or null for none
// extension default: .clarity.html (ClarityEngine) / .php (NativeEngine)

// Filters (ClarityEngine-specific)
$view->addFilter('currency', fn($v) => number_format($v, 2) . ' €');
$view->setCachePath('/var/cache/clarity');
$view->flushCache();  // invalidate all compiled templates

// Named namespaces
$view->addNamespace('admin', __DIR__ . '/../views/admin');
echo $view->render('admin::dashboard.index'); // resolves to namespace path

// Global view variables
$view->setVar('appName', 'MyApp');
$view->setVars(['user' => $currentUser, 'locale' => 'en']);

// Rendering
$html    = $view->render('home/index', ['title' => 'Home']); // with layout
$partial = $view->renderPartial('partials/header', $vars);   // no layout applied
$layout  = $view->renderLayout('layouts/print', $content, $vars);
```

---

## Crypt

`Merlin\Crypt` provides static authenticated encryption. It automatically selects the best available cipher (`libsodium` ChaCha20-Poly1305 preferred, OpenSSL AES-256-GCM as fallback).

```php
use Merlin\Crypt;

$encrypted = Crypt::encrypt('secret value', $key);
$plain     = Crypt::decrypt($encrypted, $key);   // null on failure / tamper

// Explicit cipher
Crypt::encrypt($value, $key, Crypt::CIPHER_CHACHA20_POLY1305);
Crypt::encrypt($value, $key, Crypt::CIPHER_AES_256_GCM);
Crypt::encrypt($value, $key, Crypt::CIPHER_AUTO); // default

// Availability checks
Crypt::hasSodium();    // bool
Crypt::hasOpenSSL();   // bool
Crypt::getAvailableCipher(); // string
```

---

## Minimal Web Bootstrap

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

// Register database connection(s)
$ctx->dbManager()->set('default', new Database('mysql:host=localhost;dbname=myapp', 'user', 'pass'));

// Configure view engine (ClarityEngine is the default)
$ctx->view()->setViewPath(__DIR__ . '/../views');

// Routing
$router = $ctx->router();
$router->add('GET', '/', 'IndexController::indexAction');

// Dispatcher (no constructor args)
$dispatcher = new Dispatcher();
$dispatcher->setBaseNamespace('\\App\\Controllers');
$dispatcher->addMiddleware(new SessionMiddleware());

$path   = $ctx->request()->getPath();
$method = $ctx->request()->getMethod();

$route = $router->match($path, $method);
if ($route === null) {
    Response::status(404)->send();
} else {
    $dispatcher->dispatch($route)->send();
}
```

---

## CLI Bootstrap

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Merlin\Cli\Console;

$console = new Console();
// App\Tasks is included automatically; add other namespaces as needed:
// $console->addNamespace('App\\Admin\\Tasks');
$console->process($argv[1] ?? null, $argv[2] ?? null, array_slice($argv, 3));
```

---

## Generated Files

The files under `docs/api/` are **auto-generated** from source PHPDoc. Do not edit them manually and do not update them in response to code changes.

---

## Key Conventions

- `AppContext` is always obtained via `AppContext::instance()` – never constructed manually inside framework code.
- Database connections are **always** registered through `DatabaseManager` using named roles; direct property assignment on `AppContext` is not supported.
- `Dispatcher` takes **no constructor arguments**.
- Models resolve connections through `DatabaseManager::getOrDefault($role)`, so a single registered connection is sufficient for single-DB apps.
- Route parameters are matched **by name**; type-hinted parameters are resolved via DI.
- `Model` has **no `toArray()` method** – access properties directly or build an array manually.
- `Crypt` is a **static-only** class – never instantiate it.
- All middleware must implement `Merlin\Mvc\MiddlewareInterface`.
- The default view engine is `ClarityEngine` (Clarity DSL, `.clarity.html` templates). Use `$ctx->setView(new NativeEngine())` to switch to plain-PHP templates. Template syntax reference: `docs/03b-CLARITY-ENGINE.md`.
