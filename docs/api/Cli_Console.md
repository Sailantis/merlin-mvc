# 🧩 Class: Console

**Full name:** [Merlin\Cli\Console](../../src/Cli/Console.php)

Main Console class for registering and dispatching CLI tasks.

Tasks are PHP classes that extend the base Task class and define public methods
ending with "Action". These methods can be invoked as CLI commands.

The Console class supports automatic discovery of task classes in specified
namespaces and directories, as well as a built-in help system that extracts
descriptions from doc comments.

## 📌 Public Constants

- **STYLE_ERROR** = `[
    'bg-red',
    'white',
    'bold'
]`
- **STYLE_WARN** = `[
    'byellow'
]`
- **STYLE_INFO** = `[
    'bcyan'
]`
- **STYLE_SUCCESS** = `[
    'bgreen'
]`
- **STYLE_MUTED** = `[
    'gray'
]`

## 🚀 Public methods

### __construct() · [source](../../src/Cli/Console.php#L94)

`public function __construct(string|null $scriptName = null): mixed`

Console constructor.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$scriptName` | string\|null | `null` | Optional custom script name for help output. Defaults to the basename of argv[0]. |

**➡️ Return value**

- Type: mixed


---

### setGlobalTaskHelp() · [source](../../src/Cli/Console.php#L114)

`public function setGlobalTaskHelp(string|null $help): void`

Set global help text that is appended to every help per-task detail
output. Use the same plain-text format as docblock Options sections:

--flag              One-line description
  --key=<value>       Description aligned automatically

Pass null to clear previously set help.

To suppress this section for a specific task, set
`protected bool $showGlobalHelp = false` on that task class.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$help` | string\|null | - | The help text, or null to clear. |

**➡️ Return value**

- Type: void


---

### getGlobalTaskHelp() · [source](../../src/Cli/Console.php#L122)

`public function getGlobalTaskHelp(): string|null`

Return the currently registered global task help text, or null if none is set.

**➡️ Return value**

- Type: string|null


---

### addNamespace() · [source](../../src/Cli/Console.php#L132)

`public function addNamespace(string $ns): void`

Register a namespace to search for tasks. Namespaces are resolved to directories via PSR-4 rules.

By default, "App\\Tasks" is registered. The framework's own built-in tasks are pre-registered
directly without any filesystem scan.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$ns` | string | - |  |

**➡️ Return value**

- Type: void


---

### addTaskPath() · [source](../../src/Cli/Console.php#L144)

`public function addTaskPath(string $path, bool $registerAutoload = false): void`

Register a directory path to search for task classes. This is in addition to any namespaces registered via addNamespace().

You can set $registerAutoload to true to automatically register a simple PSR-4 autoloader for this path.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$path` | string | - |  |
| `$registerAutoload` | bool | `false` |  |

**➡️ Return value**

- Type: void


---

### getDefaultAction() · [source](../../src/Cli/Console.php#L160)

`public function getDefaultAction(): string`

Get the default action method name used when no action is specified on the command line.

**➡️ Return value**

- Type: string
- Description: Default action method name (without namespace), e.g. "runAction".


---

### setDefaultAction() · [source](../../src/Cli/Console.php#L171)

`public function setDefaultAction(string $defaultAction): void`

Set the default action method name used when no action is specified on the command line.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$defaultAction` | string | - | Action method name, e.g. "runAction". |

**➡️ Return value**

- Type: void

**⚠️ Throws**

- InvalidArgumentException  If the given name is empty.


---

### clearTasks() · [source](../../src/Cli/Console.php#L180)

`public function clearTasks(): void`

Remove all registered tasks. Useful if you don't want to expose system tasks.

**➡️ Return value**

- Type: void


---

### enableColors() · [source](../../src/Cli/Console.php#L201)

`public function enableColors(bool $colors): void`

Enable or disable ANSI color output explicitly.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$colors` | bool | - |  |

**➡️ Return value**

- Type: void


---

### hasColors() · [source](../../src/Cli/Console.php#L207)

`public function hasColors(): bool`

Check whether ANSI color output is enabled.

**➡️ Return value**

- Type: bool


---

### color() · [source](../../src/Cli/Console.php#L221)

`public function color(string|int $r, int|null $g = null, int|null $b = null, mixed $background = false): string`

Generate an ANSI escape code for a custom RGB color.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$r` | string\|int | - | Either a hex color code (e.g. "#ff0000" or "bg:#00ff00" or "bg #00ff00") or the red component (0-255). |
| `$g` | int\|null | `null` | The green component (0-255), required if $r is not a hex code. |
| `$b` | int\|null | `null` | The blue component (0-255), required if $r is not a hex code. |
| `$background` | mixed | `false` | Whether this color is for background (true) or foreground (false). |

**➡️ Return value**

- Type: string
- Description: The ANSI escape code for the specified color, or an empty string if colors are disabled or input is invalid.


---

### style() · [source](../../src/Cli/Console.php#L273)

`public function style(string $text, string ...$styles): string`

Apply one or more named ANSI styles or a custom color to a string.

Style names: bold, dim, red, green, yellow, blue, magenta, cyan, white, gray, bred, bgreen, byellow, bcyan, bg-red, bg-green, bg-yellow, bg-blue, bg-magenta, bg-cyan, bg-white
Custom colors can be specified via hex code (e.g. "#ff0000" or "bg:#00ff00" or "bg #00ff00").

When color support is disabled, the text is returned unchanged.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | - |  |
| `$styles` | string | - |  |

**➡️ Return value**

- Type: string


---

### write() · [source](../../src/Cli/Console.php#L286)

`public function write(string $text = ''): void`

Write text to stdout.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | `''` |  |

**➡️ Return value**

- Type: void


---

### writeln() · [source](../../src/Cli/Console.php#L292)

`public function writeln(string $text = ''): void`

Write a line to stdout (newline appended).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | `''` |  |

**➡️ Return value**

- Type: void


---

### stderr() · [source](../../src/Cli/Console.php#L298)

`public function stderr(string $text): void`

Write text to stderr.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | - |  |

**➡️ Return value**

- Type: void


---

### stderrln() · [source](../../src/Cli/Console.php#L304)

`public function stderrln(string $text): void`

Write a line to stderr (newline appended).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | - |  |

**➡️ Return value**

- Type: void


---

### line() · [source](../../src/Cli/Console.php#L310)

`public function line(string $text): void`

Plain informational line.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | - |  |

**➡️ Return value**

- Type: void


---

### info() · [source](../../src/Cli/Console.php#L318)

`public function info(string $text): void`

Write an informational message (cyan). Newline is appended automatically.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | - |  |

**➡️ Return value**

- Type: void


---

### success() · [source](../../src/Cli/Console.php#L326)

`public function success(string $text): void`

Write a success message (green). Newline is appended automatically.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | - |  |

**➡️ Return value**

- Type: void


---

### warn() · [source](../../src/Cli/Console.php#L334)

`public function warn(string $text): void`

Write a warning message (yellow). Newline is appended automatically.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | - |  |

**➡️ Return value**

- Type: void


---

### error() · [source](../../src/Cli/Console.php#L342)

`public function error(string $text): void`

Write an error message (white on red) to STDERR. Newline is appended automatically.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | - |  |

**➡️ Return value**

- Type: void


---

### muted() · [source](../../src/Cli/Console.php#L350)

`public function muted(string $text): void`

Write a muted / dimmed message. Newline is appended automatically.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | - |  |

**➡️ Return value**

- Type: void


---

### shouldCoerceParams() · [source](../../src/Cli/Console.php#L368)

`public function shouldCoerceParams(): bool`

Check whether automatic parameter type coercion is enabled.

When enabled, string arguments that look like integers, floats, booleans,
or NULL are converted to the corresponding PHP scalar before being passed
to the action method.

**➡️ Return value**

- Type: bool
- Description: True if parameter coercion is enabled.


---

### setCoerceParams() · [source](../../src/Cli/Console.php#L378)

`public function setCoerceParams(bool $coerceParams): void`

Enable or disable automatic parameter type coercion.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$coerceParams` | bool | - | True to enable coercion, false to pass all arguments as strings. |

**➡️ Return value**

- Type: void


---

### process() · [source](../../src/Cli/Console.php#L390)

`public function process(string|null $task = null, string|null $action = null, array $params = []): void`

Process the given task, action, and parameters.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$task` | string\|null | `null` | The name of the task to execute. |
| `$action` | string\|null | `null` | The name of the action to execute within the task. |
| `$params` | array | `[]` | An array of parameters to pass to the action method. |

**➡️ Return value**

- Type: void


---

### autodiscover() · [source](../../src/Cli/Console.php#L509)

`public function autodiscover(): void`

Autodiscover tasks in all registered namespaces and paths

**➡️ Return value**

- Type: void


---

### readComposerPsr4() · [source](../../src/Cli/Console.php#L546)

`public function readComposerPsr4(): array`

Return the full PSR-4 map from the nearest composer.json.

Result is cached for the lifetime of this Console instance.

**➡️ Return value**

- Type: array
- Description: namespace prefix => absolute directory


---

### findComposerRoot() · [source](../../src/Cli/Console.php#L578)

`public function findComposerRoot(): string|null`

Walk up the directory tree from this file until composer.json is found.

Falls back to the current working directory.

**➡️ Return value**

- Type: string|null


---

### resolvePsr4Path() · [source](../../src/Cli/Console.php#L608)

`public function resolvePsr4Path(string $namespace): string|null`

Resolve a PHP namespace to an absolute directory using the PSR-4 map.

Falls back to guessing a path relative to the current working directory.

Example: "App\\Models" => "/project/src/Models"

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$namespace` | string | - |  |

**➡️ Return value**

- Type: string|null


---

### scanDirectory() · [source](../../src/Cli/Console.php#L641)

`public function scanDirectory(string $dir, string $suffix = '.php'): array`

Recursively scan $dir and return sorted absolute paths to files whose
name ends with $suffix (default ".php").

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$dir` | string | - |  |
| `$suffix` | string | `'.php'` |  |

**➡️ Return value**

- Type: array


---

### extractClassFromFile() · [source](../../src/Cli/Console.php#L664)

`public function extractClassFromFile(string $file): string|null`

Extract the fully-qualified class name from a PHP source file by
parsing its namespace declaration and the file's base name.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$file` | string | - |  |

**➡️ Return value**

- Type: string|null


---

### detectNamespace() · [source](../../src/Cli/Console.php#L681)

`public function detectNamespace(string $dir): string`

Detect the PHP namespace declared in any .php file directly inside $dir.

Returns an empty string if none is found.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$dir` | string | - |  |

**➡️ Return value**

- Type: string


---

### helpOverview() · [source](../../src/Cli/Console.php#L734)

`public function helpOverview(): void`

Built-in help task

**➡️ Return value**

- Type: void


---

### helpTask() · [source](../../src/Cli/Console.php#L822)

`public function helpTask(string $task): void`

Built-in help task for a specific task

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$task` | string | - | The name of the task to display help for |

**➡️ Return value**

- Type: void


---

### coerceParam() · [source](../../src/Cli/Console.php#L1201)

`public function coerceParam(string $param): string|int|float|bool|null`

Coerce a string parameter to int, float, bool, or null if it looks like one of those.

Otherwise return the original string. Empty string is returned as-is.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$param` | string | - | The parameter string to coerce. |

**➡️ Return value**

- Type: string|int|float|bool|null
- Description: The coerced value, or original string if no coercion applied.


---

### terminalWidth() · [source](../../src/Cli/Console.php#L1292)

`public function terminalWidth(): int`

Return detected terminal width (columns). Falls back to 80.

**➡️ Return value**

- Type: int


---

### wrapText() · [source](../../src/Cli/Console.php#L1642)

`public function wrapText(string $text, int $width): array`

Word-wrap a text block into an array of lines for the given column width.

Lines are trimmed of trailing whitespace. Empty input returns an array with one empty string.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$text` | string | - | The text to wrap. |
| `$width` | int | - | The maximum column width for wrapping. |

**➡️ Return value**

- Type: array



---

[Back to the Index ⤴](README.md)
