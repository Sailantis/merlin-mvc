# 🧩 Class: FilterRegistry

**Full name:** [Merlin\Mvc\Clarity\FilterRegistry](../../src/Mvc/Clarity/FilterRegistry.php)

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
- u                      : wrap in UnicodeString (alias: unicode)

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

### __construct() · [source](../../src/Mvc/Clarity/FilterRegistry.php#L66)

`public function __construct(): mixed`

**➡️ Return value**

- Type: mixed


---

### add() · [source](../../src/Mvc/Clarity/FilterRegistry.php#L78)

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

### has() · [source](../../src/Mvc/Clarity/FilterRegistry.php#L87)

`public function has(string $name): bool`

Check whether a named filter is registered.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |

**➡️ Return value**

- Type: bool


---

### all() · [source](../../src/Mvc/Clarity/FilterRegistry.php#L97)

`public function all(): array`

Get all registered filters as a name → callable map.

**➡️ Return value**

- Type: array



---

[Back to the Index ⤴](index.md)
