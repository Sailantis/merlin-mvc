<?php

namespace Azera\Db\Event;

use Azera\Db\Database;
use PDOException;

/**
 * Dispatched when a PDO exception is caught and being processed by
 * {@see \Azera\Db\Database::processPdoException()}.
 *
 * Listeners can use this for error logging, alerting, or metrics. The
 * exception may be re-thrown after processing; this event fires before
 * that decision is made.
 *
 * The {@see $operation} identifies which database operation failed
 * (e.g. `query`, `prepare`, `execute`, `beginTransaction`, `commit`,
 * `rollback`). The {@see $sql} and {@see $params} are only populated
 * when the failing operation had a SQL statement in scope.
 */
class DatabaseOperationFailed extends DatabaseEvent
{
    public function __construct(
        Database $database,
        public readonly PDOException $exception,
        public readonly string $operation,
        public readonly ?string $sql = null,
        public readonly ?array $params = null,
    ) {
        parent::__construct($database);
    }
}