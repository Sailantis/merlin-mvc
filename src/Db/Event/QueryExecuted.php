<?php

namespace Azera\Db\Event;

use Azera\Db\Database;

/**
 * Dispatched after a SQL query has been executed via {@see \Azera\Db\Database::query()}.
 *
 * Carries the executed SQL, bound parameters, and the wall-clock duration
 * in milliseconds. Useful for query logging, slow-query detection, and
 * debugging.
 */
class QueryExecuted extends DatabaseEvent
{
    public function __construct(
        Database $database,
        public readonly string $sql,
        public readonly ?array $params,
        public readonly float $durationMs,
    ) {
        parent::__construct($database);
    }
}