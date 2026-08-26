# 🧩 Class: LogInterceptor

**Full name:** [Azera\Aop\LogInterceptor](../../src/Aop/LogInterceptor.php)

Intercepts methods marked with [`Log`](Aop_Log.md) and logs their execution.

Logs method entry (with optional arguments), exit (with duration),
and any exceptions thrown.

## 🚀 Public methods

### __construct() · [source](../../src/Aop/LogInterceptor.php#L17)

`public function __construct(Psr\Log\LoggerInterface $logger): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$logger` | Psr\Log\LoggerInterface | - |  |

**➡️ Return value**

- Type: mixed


---

### intercept() · [source](../../src/Aop/LogInterceptor.php#L21)

`public function intercept(object $target, ReflectionMethod $method, array $args, callable $next): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$target` | object | - |  |
| `$method` | ReflectionMethod | - |  |
| `$args` | array | - |  |
| `$next` | callable | - |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
