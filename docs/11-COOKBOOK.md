# Cookbook

**Practical solutions to common problems** - A collection of real-world recipes and patterns for everyday tasks like pagination, authentication, file uploads, API responses, caching, and more. Copy, adapt, and use in your projects.

Practical recipes built with the current Merlin API.

## 1) Paginated Listing

Pagination is essential for large datasets. Use `Merlin\Db\Paginator` to paginate any query builder. The paginator runs a count() query first, then fetches the requested page using `LIMIT/OFFSET`.

```php
$paginator = User::query()
    ->where('status', 'active')
    ->orderBy('created_at DESC')
    ->paginate(page: 2, pageSize: 20);

$paginator->execute();

$meta = [
    'currentPage' => $paginator->getCurrentPage(),
    'lastPage' => $paginator->getLastPage(),
    'pageSize' => $paginator->getPageSize(),
    'totalItems' => $paginator->getTotalItems(),
    'firstItem' => $paginator->getFirstItem(),
    'lastItem' => $paginator->getLastItem(),
];

$items = $paginator->get(); // array of User models for page 2
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
class Post extends \Merlin\Mvc\Model
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
use Merlin\AppContext;

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
        ->update(['stock' => new \Merlin\Db\Sql('stock - 3')]);

    $db->commit();
} catch (\Throwable $e) {
    $db->rollback();
    throw $e;
}
```

## 8) Read/Write Split

Distribute database load by routing reads to replicas and writes to the primary server. Merlin automatically uses the appropriate connection.

```php
use Merlin\AppContext;
use Merlin\Db\Database;

$ctx = AppContext::instance();
$ctx->dbManager()->set('write', new Database('mysql:host=primary;dbname=app', 'rw', 'secret'));
$ctx->dbManager()->set('read',  new Database('mysql:host=replica;dbname=app', 'ro', 'secret'));

$users = User::findAll(['status' => 'active']); // read

$user = User::find(1);
$user->status = 'inactive';
$user->save(); // write
```

## 9) Route + Dispatcher Integration

Connect routing to the dispatcher for a complete request handling flow. This is the core pattern of any Merlin web application.

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
class CleanupTask extends \Merlin\Cli\Task
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
use Merlin\Db\Query;

// Step 1 — build the inner query independently
$activeSales = Query::new()
    ->table('orders')
    ->where('status', 'completed')
    ->where('created_at > :since', ['since' => '2025-01-01'])
    ->groupBy('user_id')
    ->columns(['user_id', 'SUM(total) AS revenue']);

// Step 2 — wrap it as a derived table
$topCustomers = Query::new()
    ->from($activeSales, 'sales')   // alias required so outer query can reference columns
    ->where('sales.revenue >', 1000)
    ->orderBy('sales.revenue DESC')
    ->limit(10)
    ->select();
```

## 12) Subquery in JOIN

Join any pre-built `Query` directly. Works with `join()`, `leftJoin()`, `innerJoin()`, `rightJoin()`, and `crossJoin()`. Provide an alias as the second argument so the outer query can reference it in conditions and columns.

```php
use Merlin\Db\Query;

// Aggregate products to their latest price
$latestPrices = Query::new()
    ->table('price_history')
    ->where('effective_date <= :today', ['today' => date('Y-m-d')])
    ->groupBy('product_id')
    ->columns(['product_id', 'MAX(price) AS current_price']);

$catalogue = Query::new()
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
use Merlin\Validation\Validator;
use Merlin\Http\Response;
use Merlin\Mvc\Controller;

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
use Merlin\Http\Response;
use Merlin\Mvc\Controller;

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

A complete login/logout flow with a `beforeAction` guard. The session is activated by `SessionMiddleware` in the dispatcher setup.

```php
// bootstrap — register the session middleware once
$dispatcher->addMiddleware(new \Merlin\Http\SessionMiddleware());
```

```php
use Merlin\Http\Response;
use Merlin\Mvc\Controller;

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

Protect any controller by overriding `beforeAction()`:

```php
use Merlin\Http\Response;
use Merlin\Mvc\Controller;

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
        $userId = $this->session()->get('user_id');
        $user   = User::find($userId);

        return $this->view()->render('account/dashboard', ['user' => $user]);
    }
}
```

## 16) CSRF Protection

Merlin has no built-in CSRF middleware — implement token-based protection yourself. The pattern below stores a token in the session and validates it on every state-changing request.

```php
// helpers.php — include in your bootstrap
function csrf_token(): string
{
    $session = \Merlin\AppContext::instance()->session();
    if (!$session->has('csrf_token')) {
        $session->set('csrf_token', bin2hex(random_bytes(32)));
    }
    return $session->get('csrf_token');
}

function csrf_verify(): bool
{
    $session = \Merlin\AppContext::instance()->session();
    $token   = \Merlin\AppContext::instance()->request()->post('_csrf_token');
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

Validate server-side — for example, in a middleware or `beforeAction`:

```php
public function beforeAction(string $action = null, array $params = []): ?Response
{
    $method = $this->request()->getMethod();
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !csrf_verify()) {
        return \Merlin\Http\Response::status(419); // token mismatch
    }
    return null;
}
```

## 17) File Upload

Access uploaded files through `Request::getUploadedFile()` (single) or `Request::getUploadedFiles()` (all). Each entry is an `UploadedFile` instance.

```php
use Merlin\Http\Response;
use Merlin\Mvc\Controller;

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

use Merlin\AppContext;
use Merlin\Http\Response;
use Merlin\Mvc\MiddlewareInterface;

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

$router->middleware('api', function (Router $r) {
    $r->add('GET', '/api/users', 'Api\UserController::indexAction');
});
```

## 19) Encrypting Sensitive Data

Use `Merlin\Crypt` to store sensitive fields (tokens, personal data, secrets) encrypted at rest. Keys should be 32 bytes of random data, loaded from an environment variable or secrets manager — never hard-coded.

```php
use Merlin\Crypt;

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
