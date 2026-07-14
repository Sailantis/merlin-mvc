# 🧩 Class: BladeAdapter

**Full name:** [Merlin\Core\Engines\Adapters\BladeAdapter](../../src/Core/Engines/Adapters/BladeAdapter.php)

Blade template engine adapter.

Wraps Laravel's Illuminate/View Blade compiler so Merlin applications can
use `.blade.php` templates.  Requires `illuminate/view` to be installed:

```sh
composer require illuminate/view
```

Blade does **not** support pipe-style filters.  Use `addDirective()`
to register custom `@directiveName(...)` syntax instead.

Cache location: `sys_get_temp_dir()/blade_cache` (override with `setCachePath()`)

## 🚀 Public methods

### __construct() · [source](../../src/Core/Engines/Adapters/BladeAdapter.php#L30)

`public function __construct(array $vars = []): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$vars` | array | `[]` |  |

**➡️ Return value**

- Type: mixed


---

### setCachePath() · [source](../../src/Core/Engines/Adapters/BladeAdapter.php#L46)

`public function setCachePath(string $path): static`

Set the directory where compiled templates should be cached.

Forces re-initialisation of the Blade compiler on the next render so the
new path takes effect.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$path` | string | - |  |

**➡️ Return value**

- Type: static


---

### getCachePath() · [source](../../src/Core/Engines/Adapters/BladeAdapter.php#L55)

`public function getCachePath(): string`

Get the currently configured cache directory.

**➡️ Return value**

- Type: string


---

### flushCache() · [source](../../src/Core/Engines/Adapters/BladeAdapter.php#L61)

`public function flushCache(): static`

Flush all cached compiled templates.

**➡️ Return value**

- Type: static


---

### addNamespace() · [source](../../src/Core/Engines/Adapters/BladeAdapter.php#L86)

`public function addNamespace(string $name, string $path): static`

Add a namespace for view resolution.

Also registers the namespace as a Blade hint path so templates can use
`namespace::view.name` syntax.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Namespace name to register. |
| `$path` | string | - | Filesystem path corresponding to the namespace. |

**➡️ Return value**

- Type: static


---

### addDirective() · [source](../../src/Core/Engines/Adapters/BladeAdapter.php#L109)

`public function addDirective(string $name, callable $handler): static`

Register a custom Blade directive.

Blade does not support pipe-style filters; use this method to add
custom `@name(...)` syntax instead.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Directive name without the `@` prefix. |
| `$handler` | callable | - | fn(?string $expression): string — must return PHP code. |

**➡️ Return value**

- Type: static


---

### addFilter() · [source](../../src/Core/Engines/Adapters/BladeAdapter.php#L124)

`public function addFilter(string $name, callable $fn): static`

Register a custom filter callable.

Blade does not support pipe-style filters.  Use `addDirective()`
to register a custom `@{$name}` directive instead.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Filter name used in templates (e.g. 'currency'). |
| `$fn` | callable | - | fn($value, ...$args): mixed |

**➡️ Return value**

- Type: static

**⚠️ Throws**

- LogicException  Always.


---

### addFunction() · [source](../../src/Core/Engines/Adapters/BladeAdapter.php#L141)

`public function addFunction(string $name, callable $fn): static`

Register a custom function callable.

Blade does not have a standalone function concept equivalent to
Twig/Plates.  Use `addDirective()` to register a custom
`@{$name}(...)` directive instead.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Function name used in templates (e.g. 'formatDate'). |
| `$fn` | callable | - | fn(...$args): mixed |

**➡️ Return value**

- Type: static

**⚠️ Throws**

- LogicException  Always.


---

### getDriver() · [source](../../src/Core/Engines/Adapters/BladeAdapter.php#L157)

`public function getDriver(): mixed`

Return the underlying engine/driver object for advanced configuration.

Returns the underlying `\Illuminate\View\Factory` instance for advanced
configuration.  Initialises Blade on first call if not already done.
Use `getDriver()->getEngineResolver()` or access `$this->bladeCompiler`
via `addDirective()` for compiler-level customisation.

**➡️ Return value**

- Type: mixed


---

### render() · [source](../../src/Core/Engines/Adapters/BladeAdapter.php#L228)

`public function render(string $view, array $vars = []): string`

Render a view (and optional layout) and return the result.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$view` | string | - | View name to render. |
| `$vars` | array | `[]` | Additional variables for this render call. |

**➡️ Return value**

- Type: string
- Description: Rendered content.


---

### renderPartial() · [source](../../src/Core/Engines/Adapters/BladeAdapter.php#L238)

`public function renderPartial(string $view, array $vars = []): string`

Render a partial view (without applying a layout) and return the output.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$view` | string | - | View name to resolve and render. |
| `$vars` | array | `[]` | Variables for this render call. |

**➡️ Return value**

- Type: string
- Description: Rendered HTML/output.


---

### renderLayout() · [source](../../src/Core/Engines/Adapters/BladeAdapter.php#L252)

`public function renderLayout(string $layout, string $content, array $vars = []): string`

Render a layout template wrapping provided content.

The layout receives the rendered view in the `content` variable.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$layout` | string | - | Layout view name. |
| `$content` | string | - | Previously rendered content. |
| `$vars` | array | `[]` | Additional variables to pass to the layout. |

**➡️ Return value**

- Type: string
- Description: Rendered layout output.



---

[Back to the Index ⤴](README.md)
