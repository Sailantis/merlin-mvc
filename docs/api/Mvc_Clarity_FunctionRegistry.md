# 🧩 Class: FunctionRegistry

**Full name:** [Merlin\Mvc\Clarity\FunctionRegistry](../../src/Mvc/Clarity/FunctionRegistry.php)

Registry of named filter callables for the Clarity template engine.

Built-in filters are registered in the constructor. User code may add
additional filters via `addFilter()`. Each filter receives the
value as its first argument and any extra pipeline arguments after it.

Built-in filters
----------------
String / text
- trim                   : strip surrounding whitespace
- upper                  : mb_strtoupper
- lower                  : mb_strtolower
- capitalize             : first character upper, rest lower
- title                  : title-case every word
- nl2br                  : insert <br> before newlines (use |> raw)
- replace($search,$repl) : str_replace
- split($delim[,$limit]) : explode into array
- join($glue)            : implode array to string
- slug[$sep]             : URL-friendly slug (default separator '-')
- striptags[$allowed]    : strip HTML/PHP tags
- truncate($len[,$ell])  : cut to $len chars and append $ell (default '…')
- format(...$args)       : sprintf-style string formatting
- length                 : mb_strlen for strings, count for arrays
- slice($start[,$len])   : mb_substr / array_slice
- unicode                : wrap in UnicodeString
- escape                 : htmlspecialchars (alias: esc)

Numbers
- number($dec)           : number_format with $dec decimal places (default 2)
- abs                    : absolute value
- round[$precision]      : round to given decimal places (default 0)

Dates
- date[$fmt]             : format timestamp / DateTimeInterface / date string (default 'Y-m-d')
- date_modify($modifier) : apply modifier, return Unix timestamp (int)

Arrays
- first                  : first element (or first character of string)
- last                   : last element (or last character of string)
- keys                   : array_keys
- merge($other)          : array_merge
- sort                   : sorted copy
- reverse                : array_reverse / Unicode-aware string reverse
- shuffle                : shuffled copy
- map(lambda|ref)        : array_map  — lambda: item => item.field
                                      — filter ref: "upper"
- filter[lambda|ref]     : array_filter (re-indexed) — same callable forms
- reduce(lambda|ref[,$i]): array_reduce — lambda receives implicit 'value'
                           param (current element): carry => carry + value
- batch($size[,$fill])   : split into chunks of $size, optionally padded

Utility
- json                   : json_encode (use |> raw to output as-is)
- default($fallback)     : $value ?: $fallback
- url_encode             : rawurlencode the value
- data_uri[$mime]        : base64-encoded data: URI

## 🚀 Public methods

### __construct() · [source](../../src/Mvc/Clarity/FunctionRegistry.php#L78)

`public function __construct(callable|null $includeRenderer = null): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$includeRenderer` | callable\|null | `null` |  |

**➡️ Return value**

- Type: mixed


---

### addFilter() · [source](../../src/Mvc/Clarity/FunctionRegistry.php#L92)

`public function addFilter(string $name, callable $fn): static`

Register a user-defined filter.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Filter name used in templates (e.g. 'currency'). |
| `$fn` | callable | - | Callable receiving ($value, ...$args). |

**➡️ Return value**

- Type: static


---

### hasFilter() · [source](../../src/Mvc/Clarity/FunctionRegistry.php#L102)

`public function hasFilter(string $name): bool`

Check whether a named filter is registered.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |

**➡️ Return value**

- Type: bool


---

### isCustomFilter() · [source](../../src/Mvc/Clarity/FunctionRegistry.php#L111)

`public function isCustomFilter(string $name): bool`

Returns true when $name was registered via addFilter() rather than a built-in.

Used by the compiler to decide whether to sandbox the return value.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |

**➡️ Return value**

- Type: bool


---

### allFilters() · [source](../../src/Mvc/Clarity/FunctionRegistry.php#L121)

`public function allFilters(): array`

Get all registered filters as a name → callable map.

**➡️ Return value**

- Type: array


---

### addFunction() · [source](../../src/Mvc/Clarity/FunctionRegistry.php#L133)

`public function addFunction(string $name, callable $fn): static`

Register a user-defined function.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - | Function name used in templates (e.g. 'greet'). |
| `$fn` | callable | - | Callable receiving any positional arguments. |

**➡️ Return value**

- Type: static


---

### hasFunction() · [source](../../src/Mvc/Clarity/FunctionRegistry.php#L143)

`public function hasFunction(string $name): bool`

Check whether a named function is registered.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |

**➡️ Return value**

- Type: bool


---

### isCustomFunction() · [source](../../src/Mvc/Clarity/FunctionRegistry.php#L152)

`public function isCustomFunction(string $name): bool`

Returns true when $name was registered via addFunction() rather than a built-in.

Used by the compiler to decide whether to sandbox the return value.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |

**➡️ Return value**

- Type: bool


---

### allFunctions() · [source](../../src/Mvc/Clarity/FunctionRegistry.php#L162)

`public function allFunctions(): array`

Get all registered functions as a name → callable map.

**➡️ Return value**

- Type: array


---

### castToArray() · [source](../../src/Mvc/Clarity/FunctionRegistry.php#L423)

`public static function castToArray(mixed $value): mixed`

Recursively cast values to arrays so templates never receive live
objects and cannot call methods.

Precedence:
1. JsonSerializable → jsonSerialize() then recurse
2. Objects with toArray() → toArray() then recurse
3. Other objects → get_object_vars() then recurse
4. Arrays → recurse element by element
5. Scalars / null → pass through

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$value` | mixed | - |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](index.md)
