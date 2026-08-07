<?php

namespace Azera\Tests\Log;

use PHPUnit\Framework\TestCase;
use Azera\Log\NullLogger;
use Psr\Log\LoggerInterface;

class NullLoggerTest extends TestCase
{
    public function testImplementsLoggerInterface(): void
    {
        $this->assertInstanceOf(LoggerInterface::class, new NullLogger());
    }

    public function testAllMethodsAreNoOps(): void
    {
        $logger = new NullLogger();

        // None of these should throw or return anything
        $logger->emergency('msg');
        $logger->alert('msg');
        $logger->critical('msg');
        $logger->error('msg');
        $logger->warning('msg');
        $logger->notice('msg');
        $logger->info('msg');
        $logger->debug('msg');
        $logger->log('info', 'msg');

        $this->assertTrue(true); // just verifying no exceptions
    }

    public function testMethodsAcceptContext(): void
    {
        $logger  = new NullLogger();
        $context = ['user' => 1, 'action' => 'test'];

        $logger->info('User {user} did {action}', $context);
        $logger->error('Something failed', $context);

        $this->assertTrue(true);
    }

    public function testMethodsAcceptStringable(): void
    {
        $logger     = new NullLogger();
        $stringable = new class implements \Stringable
        {
            public function __toString(): string
            {
                return 'stringable message';
            }
        };

        $logger->info($stringable);

        $this->assertTrue(true);
    }
}