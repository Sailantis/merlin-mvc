# 🧩 Class: BladeAdapter

**Full name:** [Merlin\Mvc\Engines\Adapters\BladeAdapter](../../src/Mvc/Engines/Adapters/BladeAdapter.php)

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

### __construct() · [source](../../src/Mvc/Engines/Adapters/BladeAdapter.php#L30)

`public function __construct(array $vars = []): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$vars` | array | `[]` |  |

**➡️ Return value**

- Type: mixed


---

### setCachePath() · [source](../../src/Mvc/Engines/Adapters/BladeAdapter.php#L46)

`public function setCachePath(string $path): static`

{@inheritdoc}

Forces re-initialisation of the Blade compiler on the next render so the
new path takes effect.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$path` | string | - |  |

**➡️ Return value**

- Type: static


---

### getCachePath() · [source](../../src/Mvc/Engines/Adapters/BladeAdapter.php#L55)

`public function getCachePath(): string`

{@inheritdoc}

**➡️ Return value**

- Type: string


---

### flushCache() · [source](../../src/Mvc/Engines/Adapters/BladeAdapter.php#L61)

`public function flushCache(): static`

{@inheritdoc}

**➡️ Return value**

- Type: static


---

### addNamespace() · [source](../../src/Mvc/Engines/Adapters/BladeAdapter.php#L86)

`public function addNamespace(string $name, string $path): static`

{@inheritdoc}

Also registers the namespace as a Blade hint path so templates can use
`namespace::view.name` syntax.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |
| `$path` | string | - |  |

**➡️ Return value**

- Type: static


---

### addDirective() · [source](../../src/Mvc/Engines/Adapters/BladeAdapter.php#L109)

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

### addFilter() · [source](../../src/Mvc/Engines/Adapters/BladeAdapter.php#L124)

`public function addFilter(string $name, callable $fn): static`

{@inheritdoc}

Blade does not support pipe-style filters.  Use `addDirective()`
to register a custom `@{$name}` directive instead.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |
| `$fn` | callable | - |  |

**➡️ Return value**

- Type: static

**⚠️ Throws**

- LogicException  Always.


---

### addFunction() · [source](../../src/Mvc/Engines/Adapters/BladeAdapter.php#L141)

`public function addFunction(string $name, callable $fn): static`

{@inheritdoc}

Blade does not have a standalone function concept equivalent to
Twig/Plates.  Use `addDirective()` to register a custom
`@{$name}(...)` directive instead.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |
| `$fn` | callable | - |  |

**➡️ Return value**

- Type: static

**⚠️ Throws**

- LogicException  Always.


---

### getDriver() · [source](../../src/Mvc/Engines/Adapters/BladeAdapter.php#L157)

`public function getDriver(): mixed`

{@inheritdoc}

Returns the underlying `\Illuminate\View\Factory` instance for advanced
configuration.  Initialises Blade on first call if not already done.
Use `getDriver()->getEngineResolver()` or access `$this->bladeCompiler`
via `addDirective()` for compiler-level customisation.

**➡️ Return value**

- Type: mixed


---

### render() · [source](../../src/Mvc/Engines/Adapters/BladeAdapter.php#L228)

`public function render(string $view, array $vars = []): string`

{@inheritdoc}

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$view` | string | - |  |
| `$vars` | array | `[]` |  |

**➡️ Return value**

- Type: string


---

### renderPartial() · [source](../../src/Mvc/Engines/Adapters/BladeAdapter.php#L238)

`public function renderPartial(string $view, array $vars = []): string`

{@inheritdoc}

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$view` | string | - |  |
| `$vars` | array | `[]` |  |

**➡️ Return value**

- Type: string


---

### renderLayout() · [source](../../src/Mvc/Engines/Adapters/BladeAdapter.php#L252)

`public function renderLayout(string $layout, string $content, array $vars = []): string`

{@inheritdoc}

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$layout` | string | - |  |
| `$content` | string | - |  |
| `$vars` | array | `[]` |  |

**➡️ Return value**

- Type: string



---

[Back to the Index ⤴](index.md)
