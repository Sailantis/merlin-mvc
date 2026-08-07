<?php

namespace Azera\Db\Event;

use Azera\Db\Database;
use Throwable;

/**
 * Dispatched when a reconnection attempt fails.
 */
class ReconnectFailed extends DatabaseEvent
{
    public function __construct(
        Database $database,
        public readonly Throwable $exception,
        public readonly int $attempt,
    ) {
        parent::__construct($database);
    }
}