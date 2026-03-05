# 🧩 Class: FilterRegistry

**Full name:** [Merlin\Mvc\Clarity\FilterRegistry](../../src/Mvc/Clarity/FilterRegistry.php)

Registry of named filter callables for the Clarity template engine.

Built-in filters are registered in the constructor. User code may add
additional filters via `addFilter()`. Each filter receives the
value as its first argument and any extra pipeline arguments after it.

Built-in filters
----------------
- escape / e   : htmlspecialchars (UTF-8, ENT_QUOTES)
- raw          : identity – disables auto-escaping
- trim         : trims whitespace
- upper        : strtoupper
- lower        : strtolower
- length       : strlen for strings, count for arrays
- number($dec) : number_format with $dec decimal places (default 2)
- date($fmt)   : date() formatting (default 'Y-m-d'); accepts int timestamp
                 or DateTimeInterface
- json         : json_encode

## 🚀 Public methods

### __construct() · [source](../../src/Mvc/Clarity/FilterRegistry.php#L29)

`public function __construct(): mixed`

**➡️ Return value**

- Type: mixed


---

### add() · [source](../../src/Mvc/Clarity/FilterRegistry.php#L41)

`public function add(string $name, callable $fn): static`

Register a user-defined filter.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Filter name used in templates (e.g. 'currency'). |
| `$fn` | callable | - | Callable receiving ($value, ...$args). |

**➡️ Return value**

- Type: static


---

### has() · [source](../../src/Mvc/Clarity/FilterRegistry.php#L50)

`public function has(string $name): bool`

Check whether a named filter is registered.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |

**➡️ Return value**

- Type: bool


---

### all() · [source](../../src/Mvc/Clarity/FilterRegistry.php#L60)

`public function all(): array`

Get all registered filters as a name → callable map.

**➡️ Return value**

- Type: array



---

[Back to the Index ⤴](index.md)
