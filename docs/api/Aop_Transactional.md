# 🧩 Class: Transactional

**Full name:** [Azera\Aop\Transactional](../../src/Aop/Transactional.php)

Marks a method as transactional.

The [`TransactionalInterceptor`](Aop_TransactionalInterceptor.md) wraps the method in a database
transaction: begins before execution, commits on success, rolls back
on any Throwable.

Supports nested transactions via savepoints (see Database::begin(nesting: true)).

Example:
<code>
#[Advised]
class BillingService
{
    #[Transactional]
    public function chargeSubscription(Account $a): void { ... }

    #[Transactional('analytics')]
    public function logEvent(Event $e): void { ... }
}
</code>

## 🌍 Public Properties

- `public readonly` string|null `$connection` · [source](../../src/Aop/Transactional.php)

## 🚀 Public methods

### __construct() · [source](../../src/Aop/Transactional.php#L30)

`public function __construct(string|null $connection = null): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$connection` | string\|null | `null` |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
