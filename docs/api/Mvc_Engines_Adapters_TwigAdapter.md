# 🧩 Class: TwigAdapter

**Full name:** [Merlin\Mvc\Engines\Adapters\TwigAdapter](../../src/Mvc/Engines/Adapters/TwigAdapter.php)

Twig template engine adapter.

Wraps Twig/Twig so Merlin applications can use `.twig` templates.
Requires `twig/twig` to be installed:

```sh
composer require twig/twig
```

Twig filters are registered natively and are available in templates using
the pipe syntax: `{{ value|filterName }}`.

Cache location: `sys_get_temp_dir()/twig_cache` (override with `setCachePath()`).
Pass an empty string to disable caching.

## 🚀 Public methods

### __construct() · [source](../../src/Mvc/Engines/Adapters/TwigAdapter.php#L35)

`public function __construct(array $vars = []): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$vars` | array | `[]` |  |

**➡️ Return value**

- Type: mixed


---

### setCachePath() · [source](../../src/Mvc/Engines/Adapters/TwigAdapter.php#L51)

`public function setCachePath(string $path): static`

{@inheritdoc}

Pass an empty string to disable caching entirely.
Changes take effect immediately even if Twig is already initialised.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$path` | string | - |  |

**➡️ Return value**

- Type: static


---

### getCachePath() · [source](../../src/Mvc/Engines/Adapters/TwigAdapter.php#L63)

`public function getCachePath(): string`

{@inheritdoc}

**➡️ Return value**

- Type: string


---

### flushCache() · [source](../../src/Mvc/Engines/Adapters/TwigAdapter.php#L69)

`public function flushCache(): static`

{@inheritdoc}

**➡️ Return value**

- Type: static


---

### addNamespace() · [source](../../src/Mvc/Engines/Adapters/TwigAdapter.php#L95)

`public function addNamespace(string $name, string $path): static`

{@inheritdoc}

Also registers the namespace with the Twig FilesystemLoader so templates
can reference it as `@namespace/path/to/template.twig`.  If Twig has not
been initialised yet the namespace is queued and applied on first render.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |
| `$path` | string | - |  |

**➡️ Return value**

- Type: static


---

### addFilter() · [source](../../src/Mvc/Engines/Adapters/TwigAdapter.php#L119)

`public function addFilter(string $name, callable $fn): static`

{@inheritdoc}

Registers a Twig filter callable.  Available in templates as
`{{ value|name }}` or `{{ value|name(arg1, arg2) }}`.

Can be called before or after the first render.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |
| `$fn` | callable | - |  |

**➡️ Return value**

- Type: static


---

### addFunction() · [source](../../src/Mvc/Engines/Adapters/TwigAdapter.php#L137)

`public function addFunction(string $name, callable $fn): static`

{@inheritdoc}

Registers a Twig function callable.  Available in templates as
`{{ name(arg1, arg2) }}`.

Can be called before or after the first render.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |
| `$fn` | callable | - |  |

**➡️ Return value**

- Type: static


---

### getDriver() · [source](../../src/Mvc/Engines/Adapters/TwigAdapter.php#L154)

`public function getDriver(): mixed`

{@inheritdoc}

Returns the underlying `\Twig\Environment` instance for advanced
configuration (extensions, token parsers, globals, etc.).
Initialises Twig on first call if not already done.

**➡️ Return value**

- Type: mixed


---

### render() · [source](../../src/Mvc/Engines/Adapters/TwigAdapter.php#L229)

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

### renderPartial() · [source](../../src/Mvc/Engines/Adapters/TwigAdapter.php#L239)

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

### renderLayout() · [source](../../src/Mvc/Engines/Adapters/TwigAdapter.php#L253)

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
