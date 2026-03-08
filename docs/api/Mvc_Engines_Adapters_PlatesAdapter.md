# 🧩 Class: PlatesAdapter

**Full name:** [Merlin\Mvc\Engines\Adapters\PlatesAdapter](../../src/Mvc/Engines/Adapters/PlatesAdapter.php)

Plates template engine adapter.

Wraps League/Plates so Merlin applications can use `.plates.php` templates.
Requires `league/plates` to be installed:

```sh
composer require league/plates
```

Plates does not use a disk cache; compiled output is plain PHP that the
PHP runtime (and OPcache) handle directly.

Filters are mapped to Plates *template functions*, which are called inside
templates as `$this->filterName($value)`.

## 🚀 Public methods

### addNamespace() · [source](../../src/Mvc/Engines/Adapters/PlatesAdapter.php#L38)

`public function addNamespace(string $name, string $path): static`

{@inheritdoc}

Also registers the namespace as a Plates folder so templates can use
`namespace::view` syntax.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |
| `$path` | string | - |  |

**➡️ Return value**

- Type: static


---

### addFilter() · [source](../../src/Mvc/Engines/Adapters/PlatesAdapter.php#L59)

`public function addFilter(string $name, callable $fn): static`

{@inheritdoc}

Plates does not distinguish between filters and functions; both are
registered as Plates *template functions* and called inside templates as
`$this->name($value, ...$args)`.  This method delegates to
`addFunction()`.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |
| `$fn` | callable | - |  |

**➡️ Return value**

- Type: static


---

### addFunction() · [source](../../src/Mvc/Engines/Adapters/PlatesAdapter.php#L73)

`public function addFunction(string $name, callable $fn): static`

{@inheritdoc}

Registers a Plates template function, callable inside templates as
`$this->name($arg1, $arg2)`.

Plates does not distinguish between filters and functions at the API
level; `addFilter()` is an alias for this method.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |
| `$fn` | callable | - |  |

**➡️ Return value**

- Type: static


---

### getDriver() · [source](../../src/Mvc/Engines/Adapters/PlatesAdapter.php#L87)

`public function getDriver(): mixed`

{@inheritdoc}

Returns the underlying `\League\Plates\Engine` instance for advanced
configuration (extensions, data, etc.).
Initialises Plates on first call if not already done.

**➡️ Return value**

- Type: mixed


---

### render() · [source](../../src/Mvc/Engines/Adapters/PlatesAdapter.php#L149)

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

### renderPartial() · [source](../../src/Mvc/Engines/Adapters/PlatesAdapter.php#L159)

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

### renderLayout() · [source](../../src/Mvc/Engines/Adapters/PlatesAdapter.php#L173)

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
