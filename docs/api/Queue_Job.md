# 🧩 Class: Job

**Full name:** [Azera\Queue\Job](../../src/Queue/Job.php)

Base implementation of [`JobInterface`](Queue_JobInterface.md) with sensible defaults.

Extend this class to create a job without boilerplate:

<code>
class SendWelcomeEmailJob extends Job
{
    public function __construct(private string $email) }

    public function handle(): void
    {
        Mailer::send($this->email, 'Welcome!');
    }
}
</code>

## 🚀 Public methods

### tries() · [source](../../src/Queue/Job.php#L24)

`public function tries(): int`

**➡️ Return value**

- Type: int


---

### backoff() · [source](../../src/Queue/Job.php#L29)

`public function backoff(): array|int`

**➡️ Return value**

- Type: array|int


---

### retryUntil() · [source](../../src/Queue/Job.php#L34)

`public function retryUntil(): int|null`

**➡️ Return value**

- Type: int|null


---

### failed() · [source](../../src/Queue/Job.php#L39)

`public function failed(Throwable $exception): void`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$exception` | Throwable | - |  |

**➡️ Return value**

- Type: void


---

### id() · [source](../../src/Queue/Job.php#L44)

`public function id(): string|null`

**➡️ Return value**

- Type: string|null


---

### queue() · [source](../../src/Queue/Job.php#L49)

`public function queue(): string`

**➡️ Return value**

- Type: string



---

[Back to the Index ⤴](README.md)
