<?php

namespace Azera\Event;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * No-op event dispatcher that returns every event unchanged.
 *
 * This is the default dispatcher returned by {@see \Azera\AppContext::events()}
 * when no concrete dispatcher has been registered. It guarantees that
 * `$ctx->events()->dispatch($event)` never fails even in apps that have
 * no event listeners wired up — the cost is a single method return.
 */
final class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): object
    {
        return $event;
    }
}