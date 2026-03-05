# 🧩 Class: NativeEngine

**Full name:** [Merlin\Mvc\Engines\NativeEngine](../../src/Mvc/Engines/NativeEngine.php)

Native PHP template engine.

Templates are plain `.php` files. Variables are extracted into the local
scope and the file is included directly, making this engine as fast as
hand-written PHP includes.

## 🚀 Public methods

### render() · [source](../../src/Mvc/Engines/NativeEngine.php#L24)

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

### renderPartial() · [source](../../src/Mvc/Engines/NativeEngine.php#L48)

`public function renderPartial(string $view, array $vars = []): string`

Render a partial view template and return the generated output.

Variables are merged with global view variables and extracted into the
template scope. Per-call variables override globals.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$view` | string | - | View name to resolve and render. |
| `$vars` | array | `[]` | Variables for this render call. |

**➡️ Return value**

- Type: string
- Description: Rendered HTML/output.

**⚠️ Throws**

- RuntimeException  If the view file cannot be resolved.


---

### renderLayout() · [source](../../src/Mvc/Engines/NativeEngine.php#L77)

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

[Back to the Index ⤴](index.md)
