<?php

namespace Azera\Db\Event;

use Azera\Db\Database;

/**
 * Dispatched when all reconnection attempts have been exhausted.
 */
class ReconnectAborted extends DatabaseEvent
{
    public function __construct(
        Database $database,
        public readonly int $attempts,
    ) {
        parent::__construct($database);
    }
}