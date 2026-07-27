# Logging

**Monitor and debug your application**  Set up comprehensive logging for database queries, application events, and errors. Learn how to use event listeners, integrate with popular logging libraries, and track performance metrics.

Azera supports logging through event listeners on database operations and custom middleware.
Integrate your own logger or observability solution via `Database::addListener()` hooks and middleware.

## Database Listeners

Database event listeners let you monitor queries, connection issues, and transactions. This is perfect for debugging, performance monitoring, or audit trails.

### Global listeners via DatabaseManager

The easiest way to attach listeners when using `DatabaseManager` is via `addGlobalListener()`, which applies to every connection managed by the instance  including those registered as lazy factories (the listener is stored and applied automatically when the factory resolves on first access):

```php
$ctx->dbManager()
    ->addGlobalListener(function (string $event, ...$args) {
        error_log('[db] ' . $event . ' ' . json_encode($args));
    })
    ->set('default', new Database($dsn, $user, $pass));
```

`addGlobalListener()` returns `$this`, so it chains fluently with `set()`, `setDefault()`, etc.

> **Ordering note:** If you call `set()` with an eager `Database` instance *before* `addGlobalListener()`, the listener is applied immediately to that already-resolved instance. If you call `set()` with a lazy factory (a `callable`), the listener is stored and applied the first time that role is accessed.

### Per-role listeners

Use `addListener(string $role, callable)` to target a specific connection:

```php
$ctx->dbManager()
    ->set('write', new Database($writeDsn, $user, $pass))
    ->set('read',  fn() => new Database($readDsn, $user, $pass))
    ->addListener('write', function (string $event, ...$args) {
        error_log('[write] ' . $event);
    });
```

### Direct listener on a Database instance

`addListener()` is fluent and can be chained directly on a `Database` object:

```php
$db = (new Database($dsn, $user, $pass))
    ->addListener(function (string $event, ...$args) {
        error_log('[db] ' . $event . ' ' . json_encode($args));
    });
```

## Event Reference

Every listener receives the event name as its first argument, followed by event-specific arguments:

| Event | Arguments |
|---|---|
| `db.beforeQuery` | `string $query, ?array $params` |
| `db.afterQuery` | `string $query, ?array $params` |
| `db.beforePrepare` | `string $query` |
| `db.afterPrepare` | `string $query` |
| `db.beforeExecute` | `PDOStatement $stmt, array $params` |
| `db.afterExecute` | `PDOStatement $stmt, array $params` |
| `db.beforeBegin` | `bool $nesting, int $transactionLevel` |
| `db.afterBegin` | `bool $nesting, int $transactionLevel` |
| `db.beforeCommit` | `bool $nesting, int $transactionLevel` |
| `db.afterCommit` | `bool $nesting, int $transactionLevel` |
| `db.beforeRollback` | `bool $nesting, int $transactionLevel` |
| `db.afterRollback` | `bool $nesting, int $transactionLevel` |
| `db.exception` | `PDOException $exception` |
| `db.reconnectAttempt` | `int $attempt, float $currentDelay, ?\Exception $cause` |
| `db.reconnected` | `int $attempt` |
| `db.reconnectFailed` | `\Exception $exception, int $attempt` |
| `db.reconnectAborted` | `int $attempt` |
| `db.reconnectCallbackFailed` | `\Exception $exception, int $attempt` |

> **`after*` events always fire**  `db.afterQuery`, `db.afterPrepare`, and `db.afterExecute` are emitted inside `finally` blocks, so they fire even when the query throws. Use this to measure elapsed time reliably or to clean up timing state.

### Measuring query duration

```php
$ctx->dbManager()->addGlobalListener(function (string $event, ...$args) use (&$start) {
    if ($event === 'db.beforeQuery') {
        $start = hrtime(true);
    } elseif ($event === 'db.afterQuery') {
        $ms = round((hrtime(true) - $start) / 1e6, 2);
        error_log(sprintf('[db] query took %s ms: %s', $ms, $args[0]));
    }
});
```

### Reconnect events and `setAutoReconnect()`

The reconnect events (`db.reconnectAttempt`, `db.reconnected`, etc.) are only fired when auto-reconnect is enabled on the `Database` instance. Configure it with `setAutoReconnect()`:

```php
$db = (new Database($dsn, $user, $pass))
    ->setAutoReconnect(
        enabled: true,
        maxAttempts: 5,
        retryDelay: 1.0,        // initial delay in seconds
        backoffMultiplier: 2.0, // exponential backoff factor
        maxRetryDelay: 30.0,    // cap on delay
        jitter: true,           // +-25% random jitter to avoid thundering herd
        onReconnect: function (int $attempt, Database $db) {
            error_log("Reconnected after $attempt attempt(s)");
        }
    )
    ->addListener(function (string $event, ...$args) {
        match ($event) {
            'db.reconnectAttempt' => error_log("Reconnect attempt {$args[0]}, delay {$args[1]}s"),
            'db.reconnected'      => error_log("Reconnected on attempt {$args[0]}"),
            'db.reconnectAborted' => error_log("Reconnect gave up after {$args[0]} attempt(s)"),
            default               => null,
        };
    });
```

`db.reconnectCallbackFailed` fires if the `onReconnect` callback itself throws; the reconnect is still considered successful in that case.

## Exception Handling and Logging

Capturing and logging exceptions is critical for debugging production issues. Middleware is the ideal place to implement global exception handling.

```php
use Azera\Core\MiddlewareInterface;
use Azera\AppContext;
use Azera\Http\Response;

class ExceptionMiddleware implements MiddlewareInterface
{
    public function __construct(private bool $debug = false) {}

    public function process(AppContext $context, callable $next): ?Response
    {
        try {
            return $next($context);
        } catch (\Throwable $e) {
            error_log($e);

            if ($this->debug) {
                return Response::json([
                    'error' => $e->getMessage(),
                    'trace' => $e->getTrace(),
                ], 500);
            }

            return Response::json(['error' => 'Internal Server Error'], 500);
        }
    }
}
```

Register this middleware first in your middleware stack to ensure all exceptions are caught before any other middleware short-circuits the pipeline.

## Integrating Your Own Logger

Forward database events to any PSR-3-compatible logger by attaching a global listener:

```php
$ctx->dbManager()->addGlobalListener(function (string $event, ...$args) use ($logger) {
    if ($event === 'db.exception') {
        $logger->error('db_exception', ['exception' => $args[0]]);
    } else {
        $logger->debug('db_event', ['event' => $event, 'args' => $args]);
    }
});
```

Use Monolog, Sentry, or any custom abstraction from your app.

## Practical Recommendations

- Log all query lifecycle events (`db.beforeQuery` / `db.afterQuery`) in development for debugging.
- In production, restrict logging to high-signal events: `db.exception` and the reconnect family.
- Redact sensitive values and PII from logged parameters before writing to any sink.
- Add a correlation/request ID at the middleware level and include it in every log message.
- Use `db.beforeBegin` / `db.afterCommit` / `db.afterRollback` to trace transaction boundaries in audit logs.

## Related

- [Database Queries](05-DATABASE-QUERIES.md)
- [Security](09-SECURITY.md)
- [src/Db/Database.php](../src/Db/Database.php)
- [src/Db/DatabaseManager.php](../src/Db/DatabaseManager.php)
