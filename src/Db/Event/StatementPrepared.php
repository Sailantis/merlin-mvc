<?php

namespace Azera\Db\Event;

use Azera\Db\Database;

/**
 * Dispatched after a SQL statement has been prepared via
 * {@see \Azera\Db\Database::prepare()}.
 */
class StatementPrepared extends DatabaseEvent
{
    public function __construct(
        Database $database,
        public readonly string $sql,
    ) {
        parent::__construct($database);
    }
}


