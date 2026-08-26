# 🧩 Class: Log

**Full name:** [Azera\Aop\Log](../../src/Aop/Log.php)

Marks a method for automatic logging.

The [`LogInterceptor`](Aop_LogInterceptor.md) logs method entry, exit, duration, and
any exceptions. Useful for debugging and audit trails.

Example:
<code>
#[Advised]
class PaymentService
{
    #[Log(level: 'info')]
    public function processPayment(Payment $p): Result { ... }
}
</code>

## 🌍 Public Properties

- `public readonly` string `$level` · [source](../../src/Aop/Log.php)
- `public readonly` bool `$logArgs` · [source](../../src/Aop/Log.php)

## 🚀 Public methods

### __construct() · [source](../../src/Aop/Log.php#L24)

`public function __construct(string $level = 'info', bool $logArgs = false): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$level` | string | `'info'` |  |
| `$logArgs` | bool | `false` |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
