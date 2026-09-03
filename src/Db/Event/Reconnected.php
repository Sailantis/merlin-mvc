<?php

namespace Azera\Db\Event;

use Azera\Db\Database;

/**
 * Dispatched after a successful reconnection.
 */
class Reconnected extends DatabaseEvent
{
    public function __construct(
        Database $database,
        public readonly int $attempt,
    ) {
        parent::__construct($database);
    }
}


