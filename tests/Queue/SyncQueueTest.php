<?php

namespace Azera\Tests\Queue;

use PHPUnit\Framework\TestCase;
use Azera\Queue\SyncQueue;
use Azera\Queue\QueueInterface;
use Azera\Queue\Job;
use Throwable;

class SyncQueueTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(QueueInterface::class, new SyncQueue());
    }

    public function testPushRunsJobImmediately(): void
    {
        $queue    = new SyncQueue();
        $executed = false;

        $job = new class($executed) extends Job
        {
            private bool $executed;

            public function __construct(bool &$executed)
            {
                $this->executed =& $executed;
            }

            public function handle(): void
            {
                $this->executed = true;
            }
        };

        $queue->push($job);

        $this->assertTrue($executed);
    }

    public function testPushReturnsNull(): void
    {
        $queue = new SyncQueue();

        $job = new class extends Job
        {
            public function handle(): void {}
        };

        $this->assertNull($queue->push($job));
    }

    public function testJobExceptionIsRethrown(): void
    {
        $queue = new SyncQueue();

        $job = new class extends Job
        {
            public function handle(): void
            {
                throw new \RuntimeException('job failed');
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('job failed');

        $queue->push($job);
    }

    public function testFailedCallbackCalledOnException(): void
    {
        $queue           = new SyncQueue();
        $failedCalled    = false;
        $failedException = null;

        $job = new class($failedCalled, $failedException) extends Job
        {
            private bool $failedCalled;
            private ?Throwable $failedException;

            public function __construct(bool &$failedCalled, ?Throwable &$failedException)
            {
                $this->failedCalled    =& $failedCalled;
                $this->failedException =& $failedException;
            }

            public function handle(): void
            {
                throw new \RuntimeException('boom');
            }

            public function failed(Throwable $exception): void
            {
                $this->failedCalled    = true;
                $this->failedException = $exception;
            }
        };

        try {
            $queue->push($job);
        } catch (\RuntimeException $e) {}

        $this->assertTrue($failedCalled);
        $this->assertInstanceOf(\RuntimeException::class, $failedException);
    }

    public function testRegisterWorkerOverridesHandle(): void
    {
        $queue  = new SyncQueue();
        $called = false;

        $job = new class extends Job
        {
            public function handle(): void
            {
                throw new \RuntimeException('should not be called');
            }
        };

        $queue->registerWorker($job::class, function () use (&$called) {
            $called = true;
        });

        $queue->push($job);

        $this->assertTrue($called);
    }
}