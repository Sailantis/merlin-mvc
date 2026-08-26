# 🧩 Class: AppContext

**Full name:** [Azera\AppContext](../../src/AppContext.php)

## 🚀 Public methods

### __construct() · [source](../../src/AppContext.php#L32)

`public function __construct(): mixed`

**➡️ Return value**

- Type: mixed


---

### instance() · [source](../../src/AppContext.php#L98)

`public static function instance(): static`

Get/create shared singleton instance

**➡️ Return value**

- Type: static


---

### setInstance() · [source](../../src/AppContext.php#L107)

`public static function setInstance(self $instance): void`

Set the shared singleton instance (e.g. for testing or multi-context scenarios).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$instance` | self | - |  |

**➡️ Return value**

- Type: void


---

### reset() · [source](../../src/AppContext.php#L120)

`public static function reset(): void`

Drop the shared singleton instance.

Call in test setUp()/tearDown() (or between multi-context scenarios) to
guarantee each test starts from a pristine context, instead of relying on
a previous test having called `setInstance()`. After this, the next
`instance()` call lazily builds a fresh context.

**➡️ Return value**

- Type: void


---

### request() · [source](../../src/AppContext.php#L132)

`public function request(): Azera\Http\Request`

Get the HttpRequest instance. If it doesn't exist, it will be created.

**➡️ Return value**

- Type: [Request](Http_Request.md)
- Description: The HttpRequest instance.


---

### view() · [source](../../src/AppContext.php#L142)

`public function view(): Azera\Core\ViewEngine`

Get the active view engine instance. Defaults to ClarityEngine.

**➡️ Return value**

- Type: [ViewEngine](Core_ViewEngine.md)
- Description: The active view engine instance.


---

### setView() · [source](../../src/AppContext.php#L153)

`public function setView(Azera\Core\ViewEngine $engine): static`

Replace the active view engine (e.g. swap in ClarityEngine at bootstrap).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$engine` | [ViewEngine](Core_ViewEngine.md) | - | The engine to use from this point on. |

**➡️ Return value**

- Type: static


---

### cookies() · [source](../../src/AppContext.php#L166)

`public function cookies(): Azera\Http\Cookies`

Get the Cookies instance. If it doesn't exist, it will be created.

**➡️ Return value**

- Type: [Cookies](Http_Cookies.md)
- Description: The Cookies instance.


---

### dbManager() · [source](../../src/AppContext.php#L176)

`public function dbManager(): Azera\Db\DatabaseManager`

Get the DatabaseManager instance. If it doesn't exist, it will be created.

**➡️ Return value**

- Type: [DatabaseManager](Db_DatabaseManager.md)
- Description: The DatabaseManager instance.


---

### router() · [source](../../src/AppContext.php#L186)

`public function router(): Azera\Core\Router`

Get the Router instance. If it doesn't exist, it will be created.

**➡️ Return value**

- Type: [Router](Core_Router.md)
- Description: The Router instance.


---

### dispatcher() · [source](../../src/AppContext.php#L196)

`public function dispatcher(): Azera\Core\Dispatcher`

Get the Dispatcher instance. If it doesn't exist, it will be created.

**➡️ Return value**

- Type: [Dispatcher](Core_Dispatcher.md)
- Description: The Dispatcher instance.


---

### logger() · [source](../../src/AppContext.php#L208)

`public function logger(): Psr\Log\LoggerInterface`

Get the logger instance. Returns a [`NullLogger`](Log_NullLogger.md) if no logger
has been registered, so calling code can safely log without
null-checks. Register a real logger via `set(LoggerInterface::class, ...)`.

**➡️ Return value**

- Type: Psr\Log\LoggerInterface


---

### events() · [source](../../src/AppContext.php#L220)

`public function events(): Psr\EventDispatcher\EventDispatcherInterface`

Get the event dispatcher. Returns a [`NullEventDispatcher`](Event_NullEventDispatcher.md) if
none has been registered, so `dispatch()` is always safe. Register
a real dispatcher via `set(EventDispatcherInterface::class, ...)`.

**➡️ Return value**

- Type: Psr\EventDispatcher\EventDispatcherInterface


---

### cache() · [source](../../src/AppContext.php#L232)

`public function cache(): Psr\SimpleCache\CacheInterface`

Get the cache instance. Returns a [`NullCache`](Cache_NullCache.md) if none has been
registered (always reports a miss). Register a real cache via
`set(CacheInterface::class, ...)`.

**➡️ Return value**

- Type: Psr\SimpleCache\CacheInterface


---

### queue() · [source](../../src/AppContext.php#L248)

`public function queue(): Azera\Queue\QueueInterface`

Get the queue instance.

Unlike the other subsystems, the queue has no null implementation
because silently dropping jobs would be dangerous. If no queue is
registered, this throws a LogicException with an install hint.
Register a queue via `set(QueueInterface::class, ...)`.

**➡️ Return value**

- Type: [QueueInterface](Queue_QueueInterface.md)

**⚠️ Throws**

- LogicException  If no queue is registered.


---

### config() · [source](../../src/AppContext.php#L271)

`public function config(): Azera\Config\Config`

Get the configuration service. Lazily creates a [`Config`](Config_Config.md)
if none has been registered.

**➡️ Return value**

- Type: [Config](Config_Config.md)


---

### pipeline() · [source](../../src/AppContext.php#L294)

`public function pipeline(array $interceptors = []): Azera\Aop\Pipeline`

Create a pipeline for explicit interceptor composition.

This is the no-proxy alternative to AOP attributes. It lets you
wrap any callable with interceptors without generating proxy
classes. The same interceptors that work with the proxy AOP
also work here.

Example:
<code>
$result = $ctx->pipeline()
    ->through([new RetryInterceptor(3), new LogInterceptor($logger)])
    ->call(fn() => $service->chargeCard(100));
</code>

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$interceptors` | array | `[]` |  |

**➡️ Return value**

- Type: [Pipeline](Aop_Pipeline.md)


---

### registerInterceptor() · [source](../../src/AppContext.php#L310)

`public function registerInterceptor(string $adviceClass, Azera\Aop\InterceptorInterface $interceptor): void`

Register an interceptor for a specific advice type.

Once at least one interceptor is registered, the DI container
will proxy classes marked with [`Advised`](Aop_Advised.md) that have methods
carrying the corresponding advice attribute.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$adviceClass` | string | - | The advice attribute class. |
| `$interceptor` | [InterceptorInterface](Aop_InterceptorInterface.md) | - | The interceptor to handle it. |

**➡️ Return value**

- Type: void


---

### setAopCacheDir() · [source](../../src/AppContext.php#L346)

`public function setAopCacheDir(string|null $dir): void`

Set the AOP proxy cache directory.

Pass a path for file-based proxy generation (OPcache-cached, production).
Pass null to use eval() (development, no cache files).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$dir` | string\|null | - |  |

**➡️ Return value**

- Type: void


---

### session() · [source](../../src/AppContext.php#L405)

`public function session(): Azera\Http\Session|null`

Get the Session instance.

**➡️ Return value**

- Type: [Session](Http_Session.md)|null


---

### setSession() · [source](../../src/AppContext.php#L415)

`public function setSession(Azera\Http\Session $session): void`

Set the Session instance.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$session` | [Session](Http_Session.md) | - | The Session instance to set in the context. |

**➡️ Return value**

- Type: void


---

### route() · [source](../../src/AppContext.php#L425)

`public function route(): Azera\Core\ResolvedRoute|null`

Get the current resolved route information.

**➡️ Return value**

- Type: [ResolvedRoute](Core_ResolvedRoute.md)|null


---

### setRoute() · [source](../../src/AppContext.php#L435)

`public function setRoute(Azera\Core\ResolvedRoute $route): void`

Set the current resolved route information.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$route` | [ResolvedRoute](Core_ResolvedRoute.md) | - | The resolved route to set in the context. |

**➡️ Return value**

- Type: void


---

### clearRequestScope() · [source](../../src/AppContext.php#L460)

`public function clearRequestScope(): void`

Clear all request-scoped state on this context.

Under a persistent application server (RoadRunner, Swoole, FrankenPHP,
Octane, …) the AppContext survives across many requests. This method
resets the per-request services so the next request starts clean:

 - the built-in request-scoped properties ([`Request`](Http_Request.md), [`ResolvedRoute`](Core_ResolvedRoute.md),
   [`Session`](Http_Session.md), [`Cookies`](Http_Cookies.md)) are dropped and lazily rebuilt on demand;
 - the corresponding DI container entries are removed so accessors do not
   return a stale instance;
 - every service registered on the container that implements
   [`RequestScoped`](Lifecycle_RequestScoped.md) has its [`RequestScoped::resetState()`](Lifecycle_RequestScoped.md#resetstate) hook called.

Persistent infrastructure is deliberately left untouched — database
manager, cache/Redis backends, queue, logger and event dispatcher keep
their handles and connections alive across requests.

Safe to call repeatedly; a no-op when no request has been processed yet.

**➡️ Return value**

- Type: void


---

### set() · [source](../../src/AppContext.php#L495)

`public function set(string $id, callable|object|null $service = null): void`

Register a service instance or lazy factory in the context.

Registered callables are treated as zero-argument factories. They are invoked on
first resolution and their returned object is cached for subsequent lookups.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$id` | string | - | The identifier for the service (usually the class name). |
| `$service` | callable\|object\|null | `null` | Optional service instance or zero-argument factory to register. |

**➡️ Return value**

- Type: void


---

### has() · [source](../../src/AppContext.php#L514)

`public function has(string $id): bool`

Check if a service is registered in the context.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$id` | string | - | The identifier of the service to check. |

**➡️ Return value**

- Type: bool
- Description: True if the service is registered, false otherwise.


---

### get() · [source](../../src/AppContext.php#L532)

`public function get(string $id): object`

Get a service instance from the context.

If the service is registered as a callable, it will be invoked lazily
once and the returned object will be cached. If the service is not
registered but the identifier is a class name, it will attempt to
auto-wire and instantiate it.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$id` | string | - | The identifier of the service to retrieve. |

**➡️ Return value**

- Type: object
- Description: The service instance associated with the given identifier.

**⚠️ Throws**

- RuntimeException  If the service is not found and cannot be auto-wired.


---

### tryGet() · [source](../../src/AppContext.php#L563)

`public function tryGet(string $id): object|null`

Try to get a service instance from the context.

If the service is registered as a callable, it will be invoked lazily
once and the returned object will be cached. If the service is not
registered but the identifier is a class name, it will attempt to
auto-wire and instantiate it. Returns null if the service is not found,
or if a registered factory currently resolves to null.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$id` | string | - | The identifier of the service to retrieve. |

**➡️ Return value**

- Type: object|null
- Description: The service instance associated with the given identifier, or null if not found.


---

### getOrNull() · [source](../../src/AppContext.php#L592)

`public function getOrNull(string $id): object|null`

Get a registered service instance if it exists, or null if it does not.

Registered factories are resolved lazily. This method does not attempt
to auto-wire or instantiate classes that have not been registered
explicitly.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$id` | string | - | The identifier of the service to retrieve. |

**➡️ Return value**

- Type: object|null
- Description: The service instance associated with the given identifier, or null if not found.



---

[Back to the Index ⤴](README.md)
