<?php

namespace Azera\Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use Azera\AppContext;
use Azera\Log\NullLogger;
use Psr\Log\LoggerInterface;
use Azera\Event\NullEventDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;
use Azera\Event\EventDispatcher;
use Azera\Cache\NullCache;
use Azera\Cache\ArrayCache;
use Psr\SimpleCache\CacheInterface;
use Azera\Queue\QueueInterface;
use Azera\Queue\SyncQueue;
use Azera\Config\Config;
use PHPUnit\Framework\TestCase;

class AppContextAccessorsTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset the singleton for each test
        AppContext::setInstance(new AppContext());
    }

    public function testLoggerReturnsNullLoggerByDefault(): void
    {
        $ctx = AppContext::instance();
        $this->assertInstanceOf(NullLogger::class, $ctx->logger());
    }

    public function testLoggerReturnsRegisteredLogger(): void
    {
        $ctx    = AppContext::instance();
        $logger = new class implements LoggerInterface
        {
            public function emergency(string|\Stringable $m, array $c = []): void {}
            public function alert(string|\Stringable $m, array $c = []): void {}
            public function critical(string|\Stringable $m, array $c = []): void {}
            public function error(string|\Stringable $m, array $c = []): void {}
            public function warning(string|\Stringable $m, array $c = []): void {}
            public function notice(string|\Stringable $m, array $c = []): void {}
            public function info(string|\Stringable $m, array $c = []): void {}
            public function debug(string|\Stringable $m, array $c = []): void {}
            public function log($l, string|\Stringable $m, array $c = []): void {}
        };

        $ctx->set(LoggerInterface::class, $logger);
        $this->assertSame($logger, $ctx->logger());
    }

    public function testLoggerIsCached(): void
    {
        $ctx = AppContext::instance();
        $this->assertSame($ctx->logger(), $ctx->logger());
    }

    public function testEventsReturnsNullDispatcherByDefault(): void
    {
        $ctx = AppContext::instance();
        $this->assertInstanceOf(NullEventDispatcher::class, $ctx->events());
    }

    public function testEventsReturnsRegisteredDispatcher(): void
    {
        $ctx        = AppContext::instance();
        $dispatcher = new EventDispatcher();

        $ctx->set(EventDispatcherInterface::class, $dispatcher);
        $this->assertSame($dispatcher, $ctx->events());
    }

    public function testEventsIsCached(): void
    {
        $ctx = AppContext::instance();
        $this->assertSame($ctx->events(), $ctx->events());
    }

    public function testCacheReturnsNullCacheByDefault(): void
    {
        $ctx = AppContext::instance();
        $this->assertInstanceOf(NullCache::class, $ctx->cache());
    }

    public function testCacheReturnsRegisteredCache(): void
    {
        $ctx   = AppContext::instance();
        $cache = new ArrayCache();

        $ctx->set(CacheInterface::class, $cache);
        $this->assertSame($cache, $ctx->cache());
    }

    public function testCacheIsCached(): void
    {
        $ctx = AppContext::instance();
        $this->assertSame($ctx->cache(), $ctx->cache());
    }

    public function testQueueThrowsWhenNotRegistered(): void
    {
        $ctx = AppContext::instance();
        $this->expectException(\LogicException::class);
        $ctx->queue();
    }

    public function testQueueReturnsRegisteredQueue(): void
    {
        $ctx   = AppContext::instance();
        $queue = new SyncQueue();

        $ctx->set(QueueInterface::class, $queue);
        $this->assertSame($queue, $ctx->queue());
    }

    public function testQueueIsCached(): void
    {
        $ctx   = AppContext::instance();
        $queue = new SyncQueue();
        $ctx->set(QueueInterface::class, $queue);

        $this->assertSame($ctx->queue(), $ctx->queue());
    }

    public function testConfigReturnsConfigByDefault(): void
    {
        $ctx = AppContext::instance();
        $this->assertInstanceOf(Config::class, $ctx->config());
    }

    public function testConfigReturnsRegisteredConfig(): void
    {
        $ctx    = AppContext::instance();
        $config = new Config(['app' => ['name' => 'test']]);

        $ctx->set(Config::class, $config);
        $this->assertSame($config, $ctx->config());
        $this->assertSame('test', $ctx->config()->get('app.name'));
    }

    public function testConfigIsCached(): void
    {
        $ctx = AppContext::instance();
        $this->assertSame($ctx->config(), $ctx->config());
    }
}