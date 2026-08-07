# Queues

Azera provides a minimal queue abstraction for deferring work. The `QueueInterface` contract supports both synchronous (inline) and asynchronous (background) processing, letting you swap drivers without changing application code.

There is no PHP-FIG standard for queues, so Azera defines its own contracts.

## QueueInterface

```php
namespace Azera\Queue;

interface QueueInterface
{
    public function push(JobInterface $job): void;
    public function pop(): ?JobInterface;
    public function count(): int;
}
```

## JobInterface and Job

`JobInterface` is the contract for executable units of work. `Job` is a convenience base class:

```php
use Azera\Queue\Job;

class SendEmailJob extends Job
{
    public function __construct(
        private string $to,
        private string $subject,
        private string $body,
    ) {}

    public function handle(): void
    {
        // Perform the work
        $mailer->send($this->to, $this->subject, $this->body);
    }
}
```

## The `queue()` accessor

`AppContext::queue()` returns a `QueueInterface`. Unlike the other subsystems, the queue has **no null implementation** — silently dropping jobs would be dangerous. If no queue is registered, `queue()` throws a `LogicException` with an install hint:

```php
// Without a registered queue:
$ctx->queue(); // throws LogicException
```

Register a queue:

```php
use Azera\Queue\QueueInterface;
use Azera\Queue\SyncQueue;

// Development: synchronous processing (jobs run inline)
$ctx->set(QueueInterface::class, fn() => new SyncQueue());

// Production: async driver (companion package)
// $ctx->set(QueueInterface::class, fn() => new RedisQueue($redis));
```

## SyncQueue

`SyncQueue` processes jobs immediately when `push()` is called. It's useful for development and testing — the job's `handle()` runs in the same process, so you can debug with full stack traces:

```php
use Azera\Queue\SyncQueue;

$queue = new SyncQueue();
$queue->push(new SendEmailJob('user@example.com', 'Welcome', 'Hello!'));
// handle() is called immediately
```

## Dispatching jobs

```php
$job = new ProcessPaymentJob($orderId, $amount);
$ctx->queue()->push($job);
```

With `SyncQueue`, the job runs inline. With an async driver, it's serialized and a worker picks it up later.

## QueueException

`Azera\Queue\QueueException` is thrown for queue-level errors (e.g., serialisation failures, connection issues).

## Companion packages

Async drivers (Redis, AMQP, database-backed) live in companion packages:

- `azera/queue-redis` — Redis-backed queue (future)
- `azera/queue-db` — Database-backed queue (future)

The `SyncQueue` is always available from the core framework.