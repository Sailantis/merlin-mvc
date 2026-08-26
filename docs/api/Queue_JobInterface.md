# 🔌 Interface: JobInterface

**Full name:** [Azera\Queue\JobInterface](../../src/Queue/JobInterface.php)

A unit of work that can be pushed onto a queue.

Implementations encapsulate the work in `handle()`. The default
retry configuration (tries, backoff) can be overridden by implementing
the corresponding methods.

Example:
<code>
class SyncStripeSeatsJob implements JobInterface
{
    public function __construct(private string $subscriptionId) }

    public function handle(): void
    {
        $sub = Stripe::retrieve($this->subscriptionId);
        // ... proration logic
    }

    public function tries(): int { return 3; }
}
</code>

## 🚀 Public methods

### handle() · [source](../../src/Queue/JobInterface.php#L37)

`public function handle(): void`

Perform the job's work.

**➡️ Return value**

- Type: void

**⚠️ Throws**

- Throwable  On failure. The worker will retry if attempts remain.


---

### tries() · [source](../../src/Queue/JobInterface.php#L44)

`public function tries(): int`

Maximum number of attempts (including the first).

**➡️ Return value**

- Type: int
- Description: Default 1 (no retries).


---

### backoff() · [source](../../src/Queue/JobInterface.php#L52)

`public function backoff(): array|int`

Seconds (or array of seconds for progressive backoff) to wait
before each retry.

**➡️ Return value**

- Type: array|int
- Description: Default 0 (no delay).


---

### retryUntil() · [source](../../src/Queue/JobInterface.php#L62)

`public function retryUntil(): int|null`

Retry until a specific time or condition.

Return a Unix timestamp to cap retries, or null to rely on
`tries()` only.

**➡️ Return value**

- Type: int|null
- Description: Default null.


---

### failed() · [source](../../src/Queue/JobInterface.php#L69)

`public function failed(Throwable $exception): void`

Called when the job fails all attempts.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$exception` | Throwable | - | The last exception thrown. |

**➡️ Return value**

- Type: void


---

### id() · [source](../../src/Queue/JobInterface.php#L77)

`public function id(): string|null`

A unique identifier for the job, used for idempotency and
deduplication. Return null to disable deduplication.

**➡️ Return value**

- Type: string|null


---

### queue() · [source](../../src/Queue/JobInterface.php#L84)

`public function queue(): string`

The queue name to push the job onto.

**➡️ Return value**

- Type: string
- Description: Default 'default'.



---

[Back to the Index ⤴](README.md)
