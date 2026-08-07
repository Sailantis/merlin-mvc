<?php

namespace Azera\Db\Event;

/**
 * Base class for all database events.
 *
 * All DB events are immutable value objects. They are dispatched through
 * {@see \Azera\AppContext::events()} — the unified event system — rather
 * than the old string-based `Database::fire()` mechanism.
 *
 * Each event carries the {@see $database} instance so listeners can
 * inspect the connection (driver, transaction level, etc.) without
 * holding a separate reference.
 */
abstract class DatabaseEvent
{
    public function __construct(
        public readonly \Azera\Db\Database $database,
    ) {}
}