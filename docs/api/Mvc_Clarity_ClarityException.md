# 🧩 Class: ClarityException

**Full name:** [Merlin\Mvc\Clarity\ClarityException](../../src/Mvc/Clarity/ClarityException.php)

Exception thrown when a Clarity template fails to compile or render.

Carries the original source template file and the line number within
that template, allowing error messages to point at the `.clarity.html`
source rather than the compiled PHP cache file.

## 🔐 Public Properties

- `public` string `$templateFile` · [source](../../src/Mvc/Clarity/ClarityException.php)
- `public` int `$templateLine` · [source](../../src/Mvc/Clarity/ClarityException.php)

## 🚀 Public methods

### __construct() · [source](../../src/Mvc/Clarity/ClarityException.php#L13)

`public function __construct(string $message, string $templateFile = '', int $templateLine = 0, Throwable|null $previous = null): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$message` | string | - |  |
| `$templateFile` | string | `''` |  |
| `$templateLine` | int | `0` |  |
| `$previous` | Throwable\|null | `null` |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](index.md)
