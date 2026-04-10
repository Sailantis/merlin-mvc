# 🧩 Class: AppContext

**Full name:** [Merlin\AppContext](../../src/AppContext.php)

## 🚀 Public methods

### __construct() · [source](../../src/AppContext.php#L15)

`public function __construct(): mixed`

**➡️ Return value**

- Type: mixed


---

### instance() · [source](../../src/AppContext.php#L60)

`public static function instance(): static`

Get/create shared singleton instance

**➡️ Return value**

- Type: static


---

### setInstance() · [source](../../src/AppContext.php#L69)

`public static function setInstance(Merlin\AppContext $instance): void`

Set the shared singleton instance (e.g. for testing or multi-context scenarios).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$instance` | [AppContext](AppContext.md) | - |  |

**➡️ Return value**

- Type: void


---

### request() · [source](../../src/AppContext.php#L81)

`public function request(): Merlin\Http\Request`

Get the HttpRequest instance. If it doesn't exist, it will be created.

**➡️ Return value**

- Type: [Request](Http_Request.md)
- Description: The HttpRequest instance.


---

### view() · [source](../../src/AppContext.php#L91)

`public function view(): Merlin\Mvc\ViewEngine`

Get the active view engine instance. Defaults to ClarityEngine.

**➡️ Return value**

- Type: [ViewEngine](Mvc_ViewEngine.md)
- Description: The active view engine instance.


---

### setView() · [source](../../src/AppContext.php#L102)

`public function setView(Merlin\Mvc\ViewEngine $engine): static`

Replace the active view engine (e.g. swap in ClarityEngine at bootstrap).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$engine` | [ViewEngine](Mvc_ViewEngine.md) | - | The engine to use from this point on. |

**➡️ Return value**

- Type: static


---

### cookies() · [source](../../src/AppContext.php#L115)

`public function cookies(): Merlin\Http\Cookies`

Get the Cookies instance. If it doesn't exist, it will be created.

**➡️ Return value**

- Type: [Cookies](Http_Cookies.md)
- Description: The Cookies instance.


---

### dbManager() · [source](../../src/AppContext.php#L125)

`public function dbManager(): Merlin\Db\DatabaseManager`

Get the DatabaseManager instance. If it doesn't exist, it will be created.

**➡️ Return value**

- Type: [DatabaseManager](Db_DatabaseManager.md)
- Description: The DatabaseManager instance.


---

### router() · [source](../../src/AppContext.php#L135)

`public function router(): Merlin\Mvc\Router`

Get the Router instance. If it doesn't exist, it will be created.

**➡️ Return value**

- Type: [Router](Mvc_Router.md)
- Description: The Router instance.


---

### session() · [source](../../src/AppContext.php#L145)

`public function session(): Merlin\Http\Session|null`

Get the Session instance.

**➡️ Return value**

- Type: [Session](Http_Session.md)|null


---

### setSession() · [source](../../src/AppContext.php#L155)

`public function setSession(Merlin\Http\Session $session): void`

Set the Session instance.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$session` | [Session](Http_Session.md) | - | The Session instance to set in the context. |

**➡️ Return value**

- Type: void


---

### route() · [source](../../src/AppContext.php#L165)

`public function route(): Merlin\ResolvedRoute|null`

Get the current resolved route information.

**➡️ Return value**

- Type: [ResolvedRoute](ResolvedRoute.md)|null


---

### setRoute() · [source](../../src/AppContext.php#L175)

`public function setRoute(Merlin\ResolvedRoute $route): void`

Set the current resolved route information.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$route` | [ResolvedRoute](ResolvedRoute.md) | - | The resolved route to set in the context. |

**➡️ Return value**

- Type: void


---

### set() · [source](../../src/AppContext.php#L191)

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

### has() · [source](../../src/AppContext.php#L209)

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

### get() · [source](../../src/AppContext.php#L225)

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

### tryGet() · [source](../../src/AppContext.php#L254)

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

### getOrNull() · [source](../../src/AppContext.php#L281)

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
