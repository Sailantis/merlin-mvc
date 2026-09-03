<?php

namespace Azera\Db\Event;

use Azera\Db\Database;
use Throwable;

/**
 * Dispatched before each reconnection attempt in
 * {@see \Azera\Db\Database::handleReconnect()}.
 */
class ReconnectAttempt extends DatabaseEvent
{
    public function __construct(
        Database $database,
        public readonly int $attempt,
        public readonly float $delaySeconds,
        public readonly ?Throwable $cause,
    ) {
        parent::__construct($database);
    }
}


