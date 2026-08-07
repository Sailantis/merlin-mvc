<?php

namespace Azera\Queue;

use Throwable;

/**
 * A queue that processes jobs synchronously, immediately in the current
 * process.
 *
 * This is the default fallback when no async queue backend is
 * registered. It allows application code to be written against
 * {@see QueueInterface} from day one — switching to Redis/AMQP later
 * is a one-line bootstrap change with no code rewrite.
 *
 * SyncQueue is also the correct choice in tests and in single-request
 * PHP-FPM deployments where no background worker is available.
 */
class SyncQueue implements QueueInterface
{
    /** @var array<class-string, callable> */
    private array $workers = [];

    public function push(JobInterface $job, array $options = []): mixed
    {
        $jobClass = $job::class;

        // If a custom worker was registered, use it; otherwise call
        // the job's own handle() method.
        if (isset($this->workers[$jobClass])) {
            ($this->workers[$jobClass])($job);
            return null;
        }

        try {
            $job->handle();
        } catch (Throwable $e) {
            // SyncQueue does not retry — it runs in the current process
            // and re-throwing is the expected behaviour. Callers that
            // need retries should use an async backend.
            $job->failed($e);
            throw $e;
        }

        return null;
    }

    public function registerWorker(string $jobClass, ?callable $handler = null): void
    {
        if ($handler === null) {
            // No explicit handler — the job's handle() will be called.
            unset($this->workers[$jobClass]);
            return;
        }

        $this->workers[$jobClass] = $handler;
    }
}