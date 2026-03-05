# 🧩 Class: ClarityEngine

**Full name:** [Merlin\Mvc\Engines\ClarityEngine](../../src/Mvc/Engines/ClarityEngine.php)

Clarity template engine.

Compiles `.clarity.html` templates into isolated PHP classes that are
cached on disk.  Templates have no access to arbitrary PHP — they can
only use the variables passed to render() and the registered filters.

Usage
-----
```php
$ctx->setView(new ClarityEngine());
$ctx->view()
    ->setPath(__DIR__ . '/../views')
    ->setLayout('layouts/main');

// Register a custom filter
$ctx->view()->addFilter('currency', fn($v) => number_format($v, 2) . ' €');
```

Template extension: .clarity.html  (overridable via setExtension())

Cache location: sys_get_temp_dir()/clarity  (configurable via setCachePath())

## 🚀 Public methods

### __construct() · [source](../../src/Mvc/Engines/ClarityEngine.php#L42)

`public function __construct(array $vars = []): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$vars` | array | `[]` |  |

**➡️ Return value**

- Type: mixed


---

### addFilter() · [source](../../src/Mvc/Engines/ClarityEngine.php#L58)

`public function addFilter(string $name, callable $fn): static`

{@inheritdoc}

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |
| `$fn` | callable | - |  |

**➡️ Return value**

- Type: static


---

### setCachePath() · [source](../../src/Mvc/Engines/ClarityEngine.php#L67)

`public function setCachePath(string $path): static`

{@inheritdoc}

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$path` | string | - |  |

**➡️ Return value**

- Type: static


---

### getCachePath() · [source](../../src/Mvc/Engines/ClarityEngine.php#L76)

`public function getCachePath(): string`

{@inheritdoc}

**➡️ Return value**

- Type: string


---

### flushCache() · [source](../../src/Mvc/Engines/ClarityEngine.php#L84)

`public function flushCache(): static`

{@inheritdoc}

**➡️ Return value**

- Type: static


---

### render() · [source](../../src/Mvc/Engines/ClarityEngine.php#L97)

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

### renderPartial() · [source](../../src/Mvc/Engines/ClarityEngine.php#L111)

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

### renderLayout() · [source](../../src/Mvc/Engines/ClarityEngine.php#L134)

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
