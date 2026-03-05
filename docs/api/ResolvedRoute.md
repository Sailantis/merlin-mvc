# 🧩 Class: ResolvedRoute

**Full name:** [Merlin\ResolvedRoute](../../src/AppContext.php)

ResolvedRoute represents the fully resolved route and execution context
used by the dispatcher to invoke the matched controller and action.

## 🔐 Public Properties

- `public` string|null `$namespace` · [source](../../src/AppContext.php)
- `public` string `$controller` · [source](../../src/AppContext.php)
- `public` string `$action` · [source](../../src/AppContext.php)
- `public` array `$params` · [source](../../src/AppContext.php)
- `public` array `$vars` · [source](../../src/AppContext.php)
- `public` array `$groups` · [source](../../src/AppContext.php)
- `public` array `$override` · [source](../../src/AppContext.php)

## 🚀 Public methods

### __construct() · [source](../../src/AppContext.php#L335)

`public function __construct(string|null $namespace, string $controller, string $action, array $params, array $vars, array $groups, array $override): mixed`

Create a new ResolvedRoute instance with the given parameters.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$namespace` | string\|null | - | Effective namespace for the controller, after applying route group namespaces. Null if no namespace is used. |
| `$controller` | string | - | Resolved controller class name. |
| `$action` | string | - | Resolved action method name. |
| `$params` | array | - | Resolved action method parameters. |
| `$vars` | array | - | Associative array of route variables extracted from the URL (e.g. ['id' => '123']). |
| `$groups` | array | - | List of middleware groups to apply for this route. |
| `$override` | array | - | Associative array of route overrides (e.g. ['controller' => 'OtherController', 'action' => 'otherAction']). |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](index.md)
