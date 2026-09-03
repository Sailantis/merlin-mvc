<?php

namespace Azera\Db\Event;

use Azera\Db\Database;

/**
 * Dispatched after a transaction (or savepoint) has been started via
 * {@see \Azera\Db\Database::begin()}.
 */
class TransactionStarted extends DatabaseEvent
{
    public function __construct(
        Database $database,
        public readonly bool $nesting,
        public readonly int $level,
    ) {
        parent::__construct($database);
    }
}


