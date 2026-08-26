# 🧩 Class: CacheInterceptor

**Full name:** [Azera\Aop\CacheInterceptor](../../src/Aop/CacheInterceptor.php)

Intercepts methods marked with [`Cache`](Aop_Cache.md) and caches their return values.

Cache key resolution:
1. If a custom key template is provided, interpolate `{argName}` placeholders
   with the method arguments.
2. Otherwise, build a key from the class name, method name, and a hash
   of the arguments.

On a cache hit, the method is NOT executed — the cached value is returned.
On a miss, the method executes and the result is stored with the given TTL.

## 🚀 Public methods

### __construct() · [source](../../src/Aop/CacheInterceptor.php#L22)

`public function __construct(Psr\SimpleCache\CacheInterface $cache): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$cache` | Psr\SimpleCache\CacheInterface | - |  |

**➡️ Return value**

- Type: mixed


---

### intercept() · [source](../../src/Aop/CacheInterceptor.php#L26)

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
