# 🧩 Class: Retry

**Full name:** [Azera\Aop\Retry](../../src/Aop/Retry.php)

Marks a method as retryable.

The [`RetryInterceptor`](Aop_RetryInterceptor.md) retries the method on failure (Throwable)
up to the specified number of times, with optional backoff between
attempts.

Example:
<code>
#[Advised]
class ApiService
{
    #[Retry(times: 3, backoff: 100)]
    public function callExternalApi(): Response { ... }
}
</code>

## 🌍 Public Properties

- `public readonly` int `$times` · [source](../../src/Aop/Retry.php)
- `public readonly` int `$backoff` · [source](../../src/Aop/Retry.php)

## 🚀 Public methods

### __construct() · [source](../../src/Aop/Retry.php#L25)

`public function __construct(int $times = 3, int $backoff = 0): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$times` | int | `3` |  |
| `$backoff` | int | `0` |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
