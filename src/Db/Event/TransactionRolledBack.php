<?php

namespace Azera\Db\Event;

use Azera\Db\Database;

/**
 * Dispatched after a transaction (or savepoint) has been rolled back via
 * {@see \Azera\Db\Database::rollback()}.
 */
class TransactionRolledBack extends DatabaseEvent
{
    public function __construct(
        Database $database,
        public readonly bool $nesting,
        public readonly int $level,
    ) {
        parent::__construct($database);
    }
}


