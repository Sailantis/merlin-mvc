# 🧩 Class: NullLogger

**Full name:** [Azera\Log\NullLogger](../../src/Log/NullLogger.php)

No-op logger that discards every message.

This is the default logger returned by [`AppContext::logger()`](AppContext.md#logger)
when no concrete logger has been registered. It exists so that calling
code can safely invoke `$ctx->logger()->info(...)` without null-checks
or errors, paying only the cost of a single method call that does
nothing.

Implements the PSR-3 `LoggerInterface`, so it is interchangeable
with any PSR-3 logger (e.g. Monolog). Register a real logger via
`AppContext::set(LoggerInterface::class, $logger)`.

## 🚀 Public methods

### emergency() · [source](../../src/Log/NullLogger.php#L22)

`public function emergency(Stringable|string $message, array $context = []): void`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$message` | Stringable\|string | - |  |
| `$context` | array | `[]` |  |

**➡️ Return value**

- Type: void


---

### alert() · [source](../../src/Log/NullLogger.php#L23)

`public function alert(Stringable|string $message, array $context = []): void`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$message` | Stringable\|string | - |  |
| `$context` | array | `[]` |  |

**➡️ Return value**

- Type: void


---

### critical() · [source](../../src/Log/NullLogger.php#L24)

`public function critical(Stringable|string $message, array $context = []): void`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$message` | Stringable\|string | - |  |
| `$context` | array | `[]` |  |

**➡️ Return value**

- Type: void


---

### error() · [source](../../src/Log/NullLogger.php#L25)

`public function error(Stringable|string $message, array $context = []): void`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$message` | Stringable\|string | - |  |
| `$context` | array | `[]` |  |

**➡️ Return value**

- Type: void


---

### warning() · [source](../../src/Log/NullLogger.php#L26)

`public function warning(Stringable|string $message, array $context = []): void`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$message` | Stringable\|string | - |  |
| `$context` | array | `[]` |  |

**➡️ Return value**

- Type: void


---

### notice() · [source](../../src/Log/NullLogger.php#L27)

`public function notice(Stringable|string $message, array $context = []): void`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$message` | Stringable\|string | - |  |
| `$context` | array | `[]` |  |

**➡️ Return value**

- Type: void


---

### info() · [source](../../src/Log/NullLogger.php#L28)

`public function info(Stringable|string $message, array $context = []): void`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$message` | Stringable\|string | - |  |
| `$context` | array | `[]` |  |

**➡️ Return value**

- Type: void


---

### debug() · [source](../../src/Log/NullLogger.php#L29)

`public function debug(Stringable|string $message, array $context = []): void`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$message` | Stringable\|string | - |  |
| `$context` | array | `[]` |  |

**➡️ Return value**

- Type: void


---

### log() · [source](../../src/Log/NullLogger.php#L30)

`public function log(mixed $level, Stringable|string $message, array $context = []): void`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$level` | mixed | - |  |
| `$message` | Stringable\|string | - |  |
| `$context` | array | `[]` |  |

**➡️ Return value**

- Type: void



---

[Back to the Index ⤴](README.md)
