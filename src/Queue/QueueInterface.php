<?php

namespace Azera\Queue;

/**
 * Interface for queue backends.
 *
 * Implementations push jobs onto a backend (Redis, AMQP, DB, in-process)
 * and provide the mechanics for a worker to pop and acknowledge them.
 *
 * The core ships with {@see SyncQueue}, which processes jobs immediately
 * in the current process. Real async backends live in the
 * `sailantis/azera-queue` companion package.
 */
interface QueueInterface
{
    /**
     * Push a job onto the queue.
     *
     * @param JobInterface  $job      The job to enqueue.
     * @param array<string, mixed> $options Backend-specific options
     *   (delay, priority, queue override, etc.).
     * @return mixed The job identifier or result. For {@see SyncQueue}
     *   this is null (job ran synchronously).
     * @throws QueueException On failure to enqueue.
     */
    public function push(JobInterface $job, array $options = []): mixed;

    /**
     * Register a worker callback for a specific job class.
     *
     * For async backends, this is used by the worker process to map
     * serialized jobs back to their handlers. For {@see SyncQueue},
     * this is a no-op (jobs handle themselves).
     *
     * @param string        $jobClass The job class to register.
     * @param callable|null $handler  Optional explicit handler.
     */
    public function registerWorker(string $jobClass, ?callable $handler = null): void;
}