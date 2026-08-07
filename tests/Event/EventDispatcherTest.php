<?php

namespace Azera\Tests\Event;

use PHPUnit\Framework\TestCase;
use Azera\Event\EventDispatcher;
use Azera\Event\NullEventDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;

class NullEventDispatcherTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(EventDispatcherInterface::class, new NullEventDispatcher());
    }

    public function testDispatchReturnsEventUnchanged(): void
    {
        $dispatcher = new NullEventDispatcher();
        $event      = new \stdClass();
        $event->value = 42;

        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertSame(42, $result->value);
    }
}

class EventDispatcherTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(EventDispatcherInterface::class, new EventDispatcher());
    }

    public function testDispatchInvokesCallableListener(): void
    {
        $dispatcher = new EventDispatcher();
        $received   = [];

        $dispatcher->listen(\stdClass::class, function (object $e) use (&$received) {
            $received[] = $e;
        });

        $event = new \stdClass();
        $dispatcher->dispatch($event);

        $this->assertCount(1, $received);
        $this->assertSame($event, $received[0]);
    }

    public function testDispatchReturnsEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $event      = new \stdClass();

        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
    }

    public function testMultipleListenersRunInOrder(): void
    {
        $dispatcher = new EventDispatcher();
        $order      = [];

        $dispatcher->listen(\stdClass::class, function () use (&$order) {
            $order[] = 'first';
        });
        $dispatcher->listen(\stdClass::class, function () use (&$order) {
            $order[] = 'second';
        });
        $dispatcher->listen(\stdClass::class, function () use (&$order) {
            $order[] = 'third';
        });

        $dispatcher->dispatch(new \stdClass());

        $this->assertSame(['first', 'second', 'third'], $order);
    }

    public function testHigherPriorityRunsFirst(): void
    {
        $dispatcher = new EventDispatcher();
        $order      = [];

        $dispatcher->listen(\stdClass::class, function () use (&$order) {
            $order[] = 'low';
        }, priority: 0);
        $dispatcher->listen(\stdClass::class, function () use (&$order) {
            $order[] = 'high';
        }, priority: 10);
        $dispatcher->listen(\stdClass::class, function () use (&$order) {
            $order[] = 'mid';
        }, priority: 5);

        $dispatcher->dispatch(new \stdClass());

        $this->assertSame(['high', 'mid', 'low'], $order);
    }

    public function testStoppableEventHaltsPropagation(): void
    {
        $dispatcher = new EventDispatcher();
        $called     = [];

        $dispatcher->listen(StoppableEvent::class, function () use (&$called) {
            $called[] = 'first';
        });
        $dispatcher->listen(StoppableEvent::class, function () use (&$called) {
            $called[] = 'second';
        });

        $event = new StoppableEvent(stopAfter: 1);
        $dispatcher->dispatch($event);

        $this->assertSame(['first'], $called);
    }

    public function testListenersForParentClassFire(): void
    {
        $dispatcher = new EventDispatcher();
        $called     = false;

        $dispatcher->listen(ParentEvent::class, function () use (&$called) {
            $called = true;
        });

        $dispatcher->dispatch(new ChildEvent());

        $this->assertTrue($called);
    }

    public function testListenersForInterfaceFire(): void
    {
        $dispatcher = new EventDispatcher();
        $called     = false;

        $dispatcher->listen(EventMarker::class, function () use (&$called) {
            $called = true;
        });

        $dispatcher->dispatch(new MarkedEvent());

        $this->assertTrue($called);
    }

    public function testNoListenersReturnsEventUnchanged(): void
    {
        $dispatcher = new EventDispatcher();
        $event      = new \stdClass();

        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
    }
}

// --- Test fixtures ---

class StoppableEvent implements StoppableEventInterface
{
    private int $callCount = 0;
    public function __construct(private int $stopAfter = 1) {}
    public function isPropagationStopped(): bool
    {
        return ++$this->callCount >= $this->stopAfter;
    }
}

class ParentEvent
{
}

class ChildEvent extends ParentEvent
{
}

interface EventMarker
{
}

class MarkedEvent implements EventMarker
{
}