# Cookbook

**Practical solutions to common problems** - A collection of real-world recipes and patterns for everyday tasks like pagination, authentication, file uploads, API responses, caching, and more. Copy, adapt, and use in your projects.

Practical recipes built with the current Azera API.

## 1) Paginated Listing

Pagination is essential for large datasets. Use `Azera\Db\Paginator` to paginate any query builder. The paginator runs a count() query first, then fetches the requested page using `LIMIT/OFFSET`.

```php
$paginator = User::query()
    ->where('status', 'active')
    ->orderBy('created_at DESC')
    ->paginate(page: 2, pageSize: 20);

$paginator->execute();

$meta = [
    'currentPage' => $paginator->currentPage(),
    'previousPage' => $paginator->previousPage(),
    'nextPage' => $paginator->nextPage(),
    'lastPage' => $paginator->lastPage(),
    'pageSize' => $paginator->pageSize(),
    'totalItems' => $paginator->totalItems(),
    'firstItem' => $paginator->firstItem(),
    'lastItem' => $paginator->lastItem(),
];

$users = $paginator->get(); // array of User models for page 2
```

## 2) Find or Create

Atomically find an existing record or create it if it doesn't exist. Useful for ensuring unique constraints while avoiding race conditions.

```php
$user = User::firstOrCreate(
    ['email' => 'jane@example.com'],
    ['username' => 'jane']
);
```

## 3) Update or Create

Similar to find or create, but always updates the record with new data if it exists. Perfect for upsert operations.

```php
$user = User::updateOrCreate(
    ['email' => 'jane@example.com'],
    ['username' => 'jane.doe', 'status' => 'active']
);
```

## 4) Search by Dynamic Filters

Build flexible search queries that adapt based on which filters the user provides. Only add conditions for present filters to keep queries efficient.

```php
$query = User::query();

if (!empty($filters['email'])) {
    $query->where('email', $filters['email']);
}

if (!empty($filters['created_after'])) {
    $query->where('created_at >= :created_after', ['created_after' => $filters['created_after']]);
}

if (!empty($filters['roles'])) {
    $query->inWhere('role', $filters['roles']);
}

$rows = $query->orderBy('id DESC')->select();
```

## 5) Safe Bulk Update

Update multiple records that match a condition. Always use WHERE clauses to prevent accidentally modifying all rows.

```php
$affected = User::query()
    ->where('last_login < :cutoff', ['cutoff' => '2025-01-01'])
    ->update(['status' => 'inactive']);
```

## 6) Soft Delete Pattern

Instead of permanently deleting records, mark them as deleted with a timestamp. This allows recovery and maintains referential integrity.

```php
class Post extends \Azera\Core\Model
{
    public int $id;
    public string $title;
    public ?string $deleted_at = null;

    public function softDelete(): bool
    {
        $this->deleted_at = date('Y-m-d H:i:s');
        return $this->save();
    }
}
```

## 7) Transaction with Multiple Writes

Wrap related database operations in a transaction to ensure data consistency. If any operation fails, all changes are rolled back.

```php
use Azera\AppContext;

$db = AppContext::instance()->dbManager()->getDefault();

$db->begin();
try {
    $orderId = Order::query()->insert([
        'user_id' => 1,
        'status' => 'open',
    ]);

    OrderItem::query()->insert([
        'order_id' => $orderId,
        'product_id' => 2,
        'qty' => 3,
    ]);

    Product::query()
        ->where('id', 2)
        ->update(['stock' => new \Azera\Db\Sql('stock - 3')]);

    $db->commit();
} catch (\Throwable $e) {
    $db->rollback();
    throw $e;
}
```

## 8) Read/Write Split

Distribute database load by routing reads to replicas and writes to the primary server. Azera automatically uses the appropriate connection.

```php
use Azera\AppContext;
use Azera\Db\Database;

$ctx = AppContext::instance();
$ctx->dbManager()->set('write', new Database('mysql:host=primary;dbname=app', 'rw', 'secret'));
$ctx->dbManager()->set('read',  new Database('mysql:host=replica;dbname=app', 'ro', 'secret'));

$users = User::findAll(['status' => 'active']); // read

$user = User::find(1);
$user->status = 'inactive';
$user->save(); // write
```

## 9) Route + Dispatcher Integration

Connect routing to the dispatcher for a complete request handling flow. This is the core pattern of any Azera web application.

```php
$router->add('GET', '/users/{id:int}', 'UserController::viewAction');
$route = $router->match('/users/7', 'GET');

if ($route !== null) {
    $response = $dispatcher->dispatch($route);
    $response->send();
}
```

## 10) CLI Cleanup Task

Create maintenance tasks for scheduled cleanup operations. Perfect for cron jobs that need to trim old data.

```php
class CleanupTask extends \Azera\Cli\Task
{
    public function sessionsAction(int $days = 30): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = Session::query()
            ->where('last_seen < :cutoff', ['cutoff' => $cutoff])
            ->delete();

        echo "Deleted {$deleted} sessions\n";
    }
}
```

## 11) Subquery as Derived Table (FROM)

Use a `Query` instance as the `FROM` source to pre-aggregate or pre-filter data before the outer query processes it. Bind parameters from the subquery are automatically carried over — no manual merging required.

```php
use Azera\Db\Query;

// Step 1 — build the inner query independently
$activeSales = Query::raw()
    ->table('orders')
    ->where('status', 'completed')
    ->where('created_at > :since', ['since' => '2025-01-01'])
    ->groupBy('user_id')
    ->columns(['user_id', 'SUM(total) AS revenue']);

// Step 2 — wrap it as a derived table
$topCustomers = Query::raw()
    ->from($activeSales, 'sales')   // alias required so outer query can reference columns
    ->where('sales.revenue >', 1000)
    ->orderBy('sales.revenue DESC')
    ->limit(10)
    ->select();
```

## 12) Subquery in JOIN

Join any pre-built `Query` directly. Works with `join()`, `leftJoin()`, `innerJoin()`, `rightJoin()`, and `crossJoin()`. Provide an alias as the second argument so the outer query can reference it in conditions and columns.

```php
use Azera\Db\Query;

// Aggregate products to their latest price
$latestPrices = Query::raw()
    ->table('price_history')
    ->where('effective_date <= :today', ['today' => date('Y-m-d')])
    ->groupBy('product_id')
    ->columns(['product_id', 'MAX(price) AS current_price']);

$catalogue = Query::raw()
    ->table('products', 'p')
    ->leftJoin($latestPrices, 'lp', 'lp.product_id = p.id')
    ->columns(['p.name', 'p.sku', 'lp.current_price'])
    ->where('p.active', 1)
    ->orderBy('p.name')
    ->select();
```

## 13) Validate Form Input and Save

Combine the `Validator` with model methods to validate, coerce, and persist data in one clean flow. `$v->validated()` returns only the fields that passed, which drops any unexpected input automatically.

```php
use Azera\Validation\Validator;
use Azera\Http\Response;
use Azera\Core\Controller;

class UserController extends Controller
{
    public function createAction(): Response|array
    {
        $v = new Validator($this->request()->post());

        $v->field('name')->required()->string()->min(2)->max(100);
        $v->field('email')->required()->email()->max(255);
        $v->field('role')->required()->in(['admin', 'editor', 'viewer']);
        $v->field('bio')->optional()->string()->max(500);

        if ($v->fails()) {
            return Response::json(['errors' => $v->errors()], 422);
        }

        $user = User::create($v->validated());

        return ['id' => $user->id, 'email' => $user->email];
    }

    public function updateAction(int $id): Response|array
    {
        $user = User::findOrFail($id);

        $v = new Validator($this->request()->post());
        $v->field('name')->optional()->string()->min(2)->max(100);
        $v->field('email')->optional()->email()->max(255);

        if ($v->fails()) {
            return Response::json(['errors' => $v->errors()], 422);
        }

        foreach ($v->validated() as $key => $value) {
            $user->$key = $value;
        }
        $user->save();

        return ['id' => $user->id];
    }
}
```

## 14) JSON API Controller

Return arrays directly from action methods — the `Dispatcher` automatically serialises them as `application/json`. Use `Response::json()` when you need a non-200 status code.

```php
use Azera\Http\Response;
use Azera\Core\Controller;

class ArticleController extends Controller
{
    // GET /articles → 200 JSON
    public function indexAction(): array
    {
        $articles = Article::findAll(['published' => 1]);

        return array_map(fn($a) => [
            'id'    => $a->id,
            'title' => $a->title,
            'slug'  => $a->slug,
        ], $articles->toArray());
    }

    // GET /articles/{id} → 200 JSON or 404
    public function showAction(int $id): Response|array
    {
        $article = Article::find($id);
        if ($article === null) {
            return Response::json(['error' => 'Not found'], 404);
        }

        return ['id' => $article->id, 'title' => $article->title, 'body' => $article->body];
    }

    // DELETE /articles/{id} → 204 No Content
    public function deleteAction(int $id): ?Response
    {
        $article = Article::findOrFail($id);
        $article->delete();

        return Response::status(204);
    }
}
```

## 15) Session-based Authentication

A complete login/logout flow with an auth middleware guard. The session is activated by `SessionMiddleware` in the dispatcher setup.

```php
// bootstrap — register the session middleware once
$dispatcher->addMiddleware(new \Azera\Http\SessionMiddleware());
```

```php
use Azera\Http\Response;
use Azera\Core\Controller;

class AuthController extends Controller
{
    public function loginAction(): Response|array
    {
        $email    = $this->request()->post('email', '');
        $password = $this->request()->post('password', '');

        $user = User::findOne(['email' => $email]);

        if ($user === null || !password_verify($password, $user->password_hash)) {
            return Response::json(['error' => 'Invalid credentials'], 401);
        }

        $this->session()->set('user_id', $user->id);
        $this->session()->regenerate(); // prevent session fixation

        return ['ok' => true];
    }

    public function logoutAction(): Response
    {
        $this->session()->destroy();
        return Response::redirect('/login');
    }
}
```

Protect any controller by attaching an auth middleware to its `$middleware` property:

```php
use Azera\Http\Response;
use Azera\Core\Controller;

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

class AccountController extends Controller
{
    protected array $middlewares = [AuthMiddleware::class];

    public function dashboardAction(): string
    {
        $userId = $this->session()->get('user_id');
        $user   = User::find($userId);

        return $this->view()->render('account/dashboard', ['user' => $user]);
    }
}
```

## 16) CSRF Protection

Azera has no built-in CSRF middleware — implement token-based protection yourself. The pattern below stores a token in the session and validates it on every state-changing request.

```php
// helpers.php — include in your bootstrap
function csrf_token(): string
{
    $session = \Azera\AppContext::instance()->session();
    if (!$session->has('csrf_token')) {
        $session->set('csrf_token', bin2hex(random_bytes(32)));
    }
    return $session->get('csrf_token');
}

function csrf_verify(): bool
{
    $session = \Azera\AppContext::instance()->session();
    $token   = \Azera\AppContext::instance()->request()->post('_csrf_token');
    return hash_equals($session->get('csrf_token', ''), (string) $token);
}
```

Embed the token in every HTML form:

```php
<!-- views/posts/create.php -->
<form method="post" action="/posts">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
    ...
</form>
```

Validate server-side with a middleware:

```php
public function process(AppContext $context, callable $next): ?Response
{
    $method = $context->request()->getMethod();
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !csrf_verify()) {
        return \Azera\Http\Response::status(419); // token mismatch
    }
    return $next($context);
}
```

## 17) File Upload

Access uploaded files through `Request::getUploadedFile()` (single) or `Request::getUploadedFiles()` (all). Each entry is an `UploadedFile` instance.

```php
use Azera\Http\Response;
use Azera\Core\Controller;

class AvatarController extends Controller
{
    public function uploadAction(): Response|array
    {
        $file = $this->request()->getUploadedFile('avatar');

        if ($file === null || !$file->isValid()) {
            return Response::json(['error' => 'No valid file uploaded'], 422);
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowed, true)) {
            return Response::json(['error' => 'Only JPEG, PNG, and WebP are allowed'], 422);
        }

        if ($file->getSize() > 2 * 1024 * 1024) { // 2 MB
            return Response::json(['error' => 'File must be under 2 MB'], 422);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $file->getExtension();
        $dest     = __DIR__ . '/../../public/uploads/' . $filename;

        $file->moveTo($dest);

        return ['url' => '/uploads/' . $filename];
    }
}
```

## 18) Custom Middleware

Implement `MiddlewareInterface` to add cross-cutting behavior — rate limiting, API key auth, CORS headers, etc. Return `null` to pass through; return a `Response` to short-circuit.

```php
<?php
namespace App\Middleware;

use Azera\AppContext;
use Azera\Http\Response;
use Azera\Core\MiddlewareInterface;

class ApiKeyMiddleware implements MiddlewareInterface
{
    private array $validKeys;

    public function __construct(array $validKeys)
    {
        $this->validKeys = $validKeys;
    }

    public function process(AppContext $context, callable $next): ?Response
    {
        $key = $context->request()->getServer('HTTP_X_API_KEY', '');

        if (!in_array($key, $this->validKeys, true)) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        return $next($context); // continue the pipeline
    }
}
```

Register globally or as a named group:

```php
// every request
$dispatcher->addMiddleware(new App\Middleware\ApiKeyMiddleware(['key-abc', 'key-xyz']));

// or only for routes inside the 'api' group
$dispatcher->defineMiddlewareGroup('api', [
    new App\Middleware\ApiKeyMiddleware(['key-abc', 'key-xyz']),
]);

$router->middleware('api');
$router->add('GET', '/api/users', 'Api\UserController::indexAction');
```

## 19) Encrypting Sensitive Data

Use `Azera\Crypt` to store sensitive fields (tokens, personal data, secrets) encrypted at rest. Keys should be 32 bytes of random data, loaded from an environment variable or secrets manager — never hard-coded.

```php
use Azera\Crypt;

$key = base64_decode($_ENV['ENCRYPTION_KEY']); // 32-byte key stored in the environment

// Encrypt before saving
$user->recovery_token = Crypt::encrypt($plainToken, $key);
$user->save();

// Decrypt after loading
$plain = Crypt::decrypt($user->recovery_token, $key);
if ($plain === null) {
    // null means the ciphertext was tampered with or the key does not match
    throw new \RuntimeException('Token integrity check failed');
}
```

Generate a key once and store it securely:

```bash
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

`Crypt` selects the best cipher available at runtime (libsodium ChaCha20-Poly1305 preferred, AES-256-GCM via OpenSSL as fallback). You do not need to care about cipher selection unless you have specific compliance requirements.

## 20) Transactional Service with AOP

Replace manual `begin/commit/rollback` boilerplate with `#[Transactional]`. Mark the class with `#[Advised]` and register it for autowiring:

```php
use Azera\Aop\Advised;
use Azera\Aop\Transactional;

#[Advised]
class OrderService
{
    #[Transactional]
    public function placeOrder(int $customerId, array $items): Order
    {
        $order = Order::create(['customer_id' => $customerId, ...]);

        foreach ($items as $item) {
            OrderItem::create(['order_id' => $order->id, ...]);
        }

        return $order;
    }
}
```

Register the service for autowiring (class string, not a factory) and register the interceptor:

```php
use Azera\Aop\TransactionalInterceptor;

$ctx->registerInterceptor(Transactional::class, new TransactionalInterceptor($ctx->dbManager()));
$ctx->set(OrderService::class); // autowired — proxy generated
```

No manual `begin/commit/rollback` — the interceptor handles it. See [AOP](16-AOP.md).

## 21) Caching Method Results

Cache expensive method results with `#[Cache]`:

```php
use Azera\Aop\Advised;
use Azera\Aop\Cache;

#[Advised]
class ReportService
{
    #[Cache(ttl: 300, key: 'report_{type}_{date}')]
    public function getReport(string $type, string $date): array
    {
        // Runs only on cache miss; result cached for 300 seconds
        return $this->buildExpensiveReport($type, $date);
    }
}
```

Register the cache interceptor:

```php
use Azera\Aop\CacheInterceptor;

$ctx->registerInterceptor(Cache::class, new CacheInterceptor($ctx->cache()));
```

The `{type}` and `{date}` placeholders are interpolated from the method arguments. See [AOP](16-AOP.md) and [Cache](14-CACHE.md).

## 22) Dispatching Events

Dispatch typed events and listen for them:

```php
// Event class
class OrderShipped
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $trackingNumber,
    ) {}
}

// Dispatch
$ctx->events()->dispatch(new OrderShipped($order->id, $tracking));

// Listen (in bootstrap)
$dispatcher->listen(OrderShipped::class, function (OrderShipped $event) use ($ctx) {
    $ctx->logger()->info('Order shipped', ['id' => $event->orderId]);
    // Send notification email, update external API, etc.
});
```

Class-string listeners are autowired through AppContext:

```php
class ShipNotificationListener
{
    public function __construct(private SmtpMailer $mailer) {}

    public function __invoke(OrderShipped $event): void
    {
        $this->mailer->send(...);
    }
}

$dispatcher->listen(OrderShipped::class, ShipNotificationListener::class);
```

See [Events](13-EVENTS.md).

## 23) Rate Limiting an Endpoint

Protect endpoints against abuse with `RateLimiter`:

```php
use Azera\Security\RateLimiter;

$limiter = new RateLimiter($ctx->cache());
$ip = $ctx->request()->server('REMOTE_ADDR', '0.0.0.0');

if (!$limiter->limit('api:' . $ip, 100, 60)) {
    return Response::json(['error' => 'Rate limit exceeded'], 429);
}
```

> Requires a persistent PSR-16 cache (Redis/Memcached) for multi-process rate limiting. `ArrayCache` only works within a single process. See [Security](18-SECURITY-ENTERPRISE.md).

## 24) CSRF Protection

Add `CsrfMiddleware` to the pipeline to protect state-changing requests:

```php
use Azera\Security\CsrfMiddleware;

$dispatcher->addMiddleware(new CsrfMiddleware());
```

In views, include the token in forms:

```html
<input type="hidden" name="_csrf_token" value="{{ csrf_token }}">
```

Generate the token in the controller:

```php
$session = $ctx->session();
$token = (new CsrfMiddleware())->ensureToken($session);
```

See [Security](18-SECURITY-ENTERPRISE.md).

## 25) Password Hashing

Use `Hasher` for secure password storage:

```php
use Azera\Security\Hasher;

$hasher = new Hasher();

// On registration
$user->password_hash = $hasher->make($plainPassword);
$user->save();

// On login
if ($hasher->verify($inputPassword, $user->password_hash)) {
    // Check if hash needs upgrading
    if ($hasher->needsRehash($user->password_hash)) {
        $user->password_hash = $hasher->make($inputPassword);
        $user->save();
    }
    // Login successful
}
```

See [Security](18-SECURITY-ENTERPRISE.md).
