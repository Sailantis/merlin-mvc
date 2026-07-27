# 🧩 Class: AppContext

**Full name:** [Azera\AppContext](../../src/AppContext.php)

## 🚀 Public methods

### __construct() · [source](../../src/AppContext.php#L16)

`public function __construct(): mixed`

**➡️ Return value**

- Type: mixed


---

### instance() · [source](../../src/AppContext.php#L65)

`public static function instance(): static`

Get/create shared singleton instance

**➡️ Return value**

- Type: static


---

### setInstance() · [source](../../src/AppContext.php#L74)

`public static function setInstance(Azera\AppContext $instance): void`

Set the shared singleton instance (e.g. for testing or multi-context scenarios).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$instance` | [AppContext](AppContext.md) | - |  |

**➡️ Return value**

- Type: void


---

### request() · [source](../../src/AppContext.php#L86)

`public function request(): Azera\Http\Request`

Get the HttpRequest instance. If it doesn't exist, it will be created.

**➡️ Return value**

- Type: [Request](Http_Request.md)
- Description: The HttpRequest instance.


---

### view() · [source](../../src/AppContext.php#L96)

`public function view(): Azera\Core\ViewEngine`

Get the active view engine instance. Defaults to ClarityEngine.

**➡️ Return value**

- Type: [ViewEngine](Core_ViewEngine.md)
- Description: The active view engine instance.


---

### setView() · [source](../../src/AppContext.php#L107)

`public function setView(Azera\Core\ViewEngine $engine): static`

Replace the active view engine (e.g. swap in ClarityEngine at bootstrap).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$engine` | [ViewEngine](Core_ViewEngine.md) | - | The engine to use from this point on. |

**➡️ Return value**

- Type: static


---

### cookies() · [source](../../src/AppContext.php#L120)

`public function cookies(): Azera\Http\Cookies`

Get the Cookies instance. If it doesn't exist, it will be created.

**➡️ Return value**

- Type: [Cookies](Http_Cookies.md)
- Description: The Cookies instance.


---

### dbManager() · [source](../../src/AppContext.php#L130)

`public function dbManager(): Azera\Db\DatabaseManager`

Get the DatabaseManager instance. If it doesn't exist, it will be created.

**➡️ Return value**

- Type: [DatabaseManager](Db_DatabaseManager.md)
- Description: The DatabaseManager instance.


---

### router() · [source](../../src/AppContext.php#L140)

`public function router(): Azera\Core\Router`

Get the Router instance. If it doesn't exist, it will be created.

**➡️ Return value**

- Type: [Router](Core_Router.md)
- Description: The Router instance.


---

### dispatcher() · [source](../../src/AppContext.php#L145)

`public function dispatcher(): Azera\Core\Dispatcher`

**➡️ Return value**

- Type: [Dispatcher](Core_Dispatcher.md)


---

### session() · [source](../../src/AppContext.php#L155)

`public function session(): Azera\Http\Session|null`

Get the Session instance.

**➡️ Return value**

- Type: [Session](Http_Session.md)|null


---

### setSession() · [source](../../src/AppContext.php#L165)

`public function setSession(Azera\Http\Session $session): void`

Set the Session instance.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$session` | [Session](Http_Session.md) | - | The Session instance to set in the context. |

**➡️ Return value**

- Type: void


---

### route() · [source](../../src/AppContext.php#L175)

`public function route(): Azera\ResolvedRoute|null`

Get the current resolved route information.

**➡️ Return value**

- Type: [ResolvedRoute](ResolvedRoute.md)|null


---

### setRoute() · [source](../../src/AppContext.php#L185)

`public function setRoute(Azera\ResolvedRoute $route): void`

Set the current resolved route information.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$route` | [ResolvedRoute](ResolvedRoute.md) | - | The resolved route to set in the context. |

**➡️ Return value**

- Type: void


---

### set() · [source](../../src/AppContext.php#L201)

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

### has() · [source](../../src/AppContext.php#L219)

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

### get() · [source](../../src/AppContext.php#L235)

`public function get(string $id): object`

Get a service instance from the context.

If the service is registered as a callable, it will be invoked lazily once and the
returned object will be cached. If the service is not registered but the identifier
is a class name, it will attempt to auto-wire and instantiate it.

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

### tryGet() · [source](../../src/AppContext.php#L264)

`public function tryGet(string $id): object|null`

Try to get a service instance from the context.

If the service is registered as a callable, it will be invoked lazily once and the
returned object will be cached. If the service is not registered but the identifier
is a class name, it will attempt to auto-wire and instantiate it. Returns null if
the service is not found, or if a registered factory currently resolves to null.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$id` | string | - | The identifier of the service to retrieve. |

**➡️ Return value**

- Type: object|null
- Description: The service instance associated with the given identifier, or null if not found.


---

### getOrNull() · [source](../../src/AppContext.php#L291)

`public function getOrNull(string $id): object|null`

Get a registered service instance if it exists, or null if it does not.

Registered factories are resolved lazily. This method does not attempt to auto-wire
or instantiate classes that have not been registered explicitly.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$id` | string | - | The identifier of the service to retrieve. |

**➡️ Return value**

- Type: object|null
- Description: The service instance associated with the given identifier, or null if not found.



---

[Back to the Index ⤴](README.md)
