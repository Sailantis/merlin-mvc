# 🧩 Class: Router

**Full name:** [Merlin\Mvc\Router](../../src/Mvc/Router.php)

## 🚀 Public methods

### __construct() · [source](../../src/Mvc/Router.php#L31)

`public function __construct(): mixed`

Create a new Router instance.

**➡️ Return value**

- Type: mixed


---

### addType() · [source](../../src/Mvc/Router.php#L63)

`public function addType(string $name, callable $validator): static`

Register a custom type validator for route parameters.

Predefined types include 'int', 'alpha', 'alnum', 'uuid', and '*' (matches anything). You can add your own types with custom validation logic. For example, you could add a 'slug' type that only allows lowercase letters, numbers, and hyphens. Once a type is registered, you can use it in your route patterns like /blog/{slug:slug}.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | The type name (e.g., 'slug', 'email') |
| `$validator` | callable | - | Function that validates a string value, returns bool |

**➡️ Return value**

- Type: static
- Description: For method chaining

**💡 Example**

```php
$router->addType('slug', fn($v) => preg_match('/^[a-z0-9-]+$/', $v));
$router->add('GET', '/blog/{slug:slug}', 'Blog::view');
```


---

### add() · [source](../../src/Mvc/Router.php#L77)

`public function add(array|string|null $method, string $pattern, array|string|null $handler = null): static`

Add a new route to the router. The route can be defined for specific HTTP methods, a URI pattern, and an optional handler that overrides the default controller/action resolution. The pattern can include static segments, typed parameters, dynamic segments for namespace/controller/action, and wildcard segments for additional parameters. Validators can be applied to dynamic parameters using predefined or custom types. For example: /user/{id:int} or /blog/{slug:slug}

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$method` | array\|string\|null | - | HTTP method(s) for the route (e.g., 'GET', ['GET', 'POST'], or '*' for all methods) |
| `$pattern` | string | - | Route pattern (e.g., '/blog/{slug}', '/{controller}/{action}/{params:*}') |
| `$handler` | array\|string\|null | `null` | Optional handler definition to override controller/action. Can be a string like 'Admin::dashboard' or an array with keys 'namespace', 'controller', 'action'. |

**➡️ Return value**

- Type: static
- Description: For method chaining


---

### setName() · [source](../../src/Mvc/Router.php#L148)

`public function setName(string $name): static`

Assign a name to the most recently added route. This allows you to generate URLs for this route using the `urlFor()` method.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | The name to assign to the route |

**➡️ Return value**

- Type: static
- Description: For method chaining

**⚠️ Throws**

- LogicException  If no route has been added yet or if the last added route is invalid


---

### hasNamedRoute() · [source](../../src/Mvc/Router.php#L167)

`public function hasNamedRoute(string $name): bool`

Check if a named route exists.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | The name of the route to check |

**➡️ Return value**

- Type: bool
- Description: True if a route with the given name exists, false otherwise


---

### urlFor() · [source](../../src/Mvc/Router.php#L181)

`public function urlFor(string $name, array $params = [], array $query = []): string`

Generate a URL for a named route, substituting parameters as needed.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | The name of the route to generate a URL for |
| `$params` | array | `[]` | Associative array of parameter values to substitute into the route pattern |
| `$query` | array | `[]` | Optional associative array of query parameters to append to the URL |

**➡️ Return value**

- Type: string
- Description: The generated URL path (e.g., "/blog/hello-world?ref=homepage")

**⚠️ Throws**

- RuntimeException  If no route with the given name exists or if required parameters are missing/invalid


---

### prefix() · [source](../../src/Mvc/Router.php#L237)

`public function prefix(string $prefix, callable|null $callback = null): static`

Define a group of routes that share a common URI prefix. When a callback is supplied, the prefix is scoped to that callback and the router restores the previous group state afterward. When omitted, the prefix stays on the stack for subsequent routes.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$prefix` | string | - | URI prefix for the group (e.g., "/admin") |
| `$callback` | callable\|null | `null` | Optional callback that receives the router instance to define routes within the group |

**➡️ Return value**

- Type: static
- Description: For method chaining

**💡 Example**

```php
$router->prefix('/admin');
$router->add('GET', '/dashboard', 'Admin::dashboard');
```


---

### middleware() · [source](../../src/Mvc/Router.php#L271)

`public function middleware(array|string $name, callable|null $callback = null): static`

Add group of middleware to be applied to all routes defined within the group. When a callback is supplied, the middleware groups are scoped to that callback and the router restores the previous stack afterward. When omitted, the middleware stays on the active stack for subsequent routes.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | array\|string | - | Middleware group name (e.g., "auth") |
| `$callback` | callable\|null | `null` | Optional callback that receives the router instance to define routes within the group |

**➡️ Return value**

- Type: static
- Description: For method chaining

**💡 Example**

```php
$router->middleware('auth');
$router->add('GET', '/admin/dashboard', 'Admin::dashboard');
```


---

### namespace() · [source](../../src/Mvc/Router.php#L307)

`public function namespace(string $namespace, callable|null $callback = null): static`

Define a group of routes that share a common namespace for their handlers. When a callback is supplied, the namespace is scoped to that callback and the router restores the previous group state afterward. When omitted, the namespace stays on the stack for subsequent routes.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$namespace` | string | - | Namespace prefix for the group (e.g., "Admin") |
| `$callback` | callable\|null | `null` | Optional callback that receives the router instance to define routes within the group |

**➡️ Return value**

- Type: static
- Description: For method chaining

**💡 Example**

```php
$router->namespace('Admin');
$router->add('GET', '/dashboard', 'Dashboard::view');
```


---

### controller() · [source](../../src/Mvc/Router.php#L344)

`public function controller(string $controller, callable|null $callback = null): static`

Define a group of routes that share a common controller. When a callback is supplied, the controller is scoped to that callback and the router restores the previous group state afterward. When omitted, the controller stays on the stack for subsequent routes.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$controller` | string | - | Controller name for the group (e.g., "Admin") |
| `$callback` | callable\|null | `null` | Optional callback that receives the router instance to define routes within the group |

**➡️ Return value**

- Type: static
- Description: For method chaining

**💡 Example**

```php
$router->controller('Admin');
$router->add('GET', '/dashboard', '::view');
```


---

### match() · [source](../../src/Mvc/Router.php#L591)

`public function match(string $uri, string $method = 'GET'): array|null`

Attempt to match the given URI and HTTP method against the registered routes.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$uri` | string | - | The request URI (path) to match, e.g. "/blog/hello-world" |
| `$method` | string | `'GET'` | The HTTP method, e.g. "GET", "POST" |

**➡️ Return value**

- Type: array|null
- Description: If a match is found, returns an array with keys 'vars', 'override', 'groups', 'wildcards'. Otherwise, returns null.



---

[Back to the Index ⤴](README.md)
