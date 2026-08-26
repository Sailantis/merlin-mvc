# 🧩 Class: Controller

**Full name:** [Azera\Core\Controller](../../src/Core/Controller.php)

Base class for controllers in Azera.

## 🚀 Public methods

### getMiddlewares() · [source](../../src/Core/Controller.php#L46)

`public function getMiddlewares(): array`

Get the middleware for the controller. Usually used by the Dispatcher to build the middleware pipeline for the current request.

**➡️ Return value**

- Type: array


---

### getActionMiddlewares() · [source](../../src/Core/Controller.php#L56)

`public function getActionMiddlewares(string $action): array`

Get the middleware for a specific action. Usually used by the Dispatcher to build the middleware pipeline for the current request.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$action` | string | - | The name of the action (e.g. "editAction") |

**➡️ Return value**

- Type: array


---

### context() · [source](../../src/Core/Controller.php#L67)

`public function context(): Azera\AppContext`

Get the current AppContext instance. Useful for accessing services or route info from the controller.

**➡️ Return value**

- Type: [AppContext](AppContext.md)


---

### request() · [source](../../src/Core/Controller.php#L76)

`public function request(): Azera\Http\Request`

Get the current Request object from the context.

**➡️ Return value**

- Type: [Request](Http_Request.md)


---

### view() · [source](../../src/Core/Controller.php#L85)

`public function view(): Azera\Core\ViewEngine`

Get the ViewEngine from the context for rendering views.

**➡️ Return value**

- Type: [ViewEngine](Core_ViewEngine.md)


---

### session() · [source](../../src/Core/Controller.php#L94)

`public function session(): Azera\Http\Session|null`

Get the Session from the context. May return null if no session is available.

**➡️ Return value**

- Type: [Session](Http_Session.md)|null


---

### cookies() · [source](../../src/Core/Controller.php#L103)

`public function cookies(): Azera\Http\Cookies`

Get the Cookies service from the context for managing cookies.

**➡️ Return value**

- Type: [Cookies](Http_Cookies.md)


---

### resolve() · [source](../../src/Core/Controller.php#L115)

`public function resolve(string $id): object`

Get a service from the context by class name.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$id` | string | - |  |

**➡️ Return value**

- Type: object


---

### db() · [source](../../src/Core/Controller.php#L123)

`public function db(): Azera\Db\DatabaseManager`

Get the database manager for persistence access.

**➡️ Return value**

- Type: [DatabaseManager](Db_DatabaseManager.md)


---

### cache() · [source](../../src/Core/Controller.php#L131)

`public function cache(): Psr\SimpleCache\CacheInterface`

Get the cache service.

**➡️ Return value**

- Type: Psr\SimpleCache\CacheInterface


---

### logger() · [source](../../src/Core/Controller.php#L139)

`public function logger(): Psr\Log\LoggerInterface`

Get the logger.

**➡️ Return value**

- Type: Psr\Log\LoggerInterface


---

### events() · [source](../../src/Core/Controller.php#L147)

`public function events(): Psr\EventDispatcher\EventDispatcherInterface`

Get the event dispatcher.

**➡️ Return value**

- Type: Psr\EventDispatcher\EventDispatcherInterface



---

[Back to the Index ⤴](README.md)
