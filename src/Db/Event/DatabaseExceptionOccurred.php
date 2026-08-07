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
 */
class DatabaseExceptionOccurred extends DatabaseEvent
{
    public function __construct(
        Database $database,
        public readonly PDOException $exception,
    ) {
        parent::__construct($database);
    }
}