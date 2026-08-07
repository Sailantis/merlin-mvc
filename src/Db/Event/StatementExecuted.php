<?php

namespace Azera\Db\Event;

use Azera\Db\Database;

/**
 * Dispatched after a previously prepared statement has been executed via
 * {@see \Azera\Db\Database::execute()}.
 */
class StatementExecuted extends DatabaseEvent
{
    public function __construct(
        Database $database,
        public readonly array $params,
        public readonly float $durationMs,
    ) {
        parent::__construct($database);
    }
}