<?php

namespace Azera\Facade;

use Azera\AppContext;
use Azera\Db\Database;

/**
 * Thin static proxy over the SQL connection registry.
 *
 * Plain static methods only — no magic container, no dynamic __callStatic
 * facade resolution. Every call goes through the AppContext singleton, so
 * it resolves the same shared connection per role the QB and ORM use.
 */
final class Db
{
    /**
     * Start a query builder on the default (or given) role's connection.
     * Delegates to the existing Query::new() — the QB stays the QB.
     */
    public static function query(?string $role = null): \Azera\Db\Query
    {
        $db = self::connection($role);

        return \Azera\Db\Query::new($db);
    }

    /**
     * Raw SQL through the tracked connection (events fire).
     */
    public static function statement(string $sql, ?array $params = null, ?string $role = null)
    {
        return self::connection($role)->query($sql, $params);
    }

    /**
     * Connection for a role (default when null).
     */
    public static function connection(?string $role = null): Database
    {
        $dbm = AppContext::instance()->dbManager();

        return $role === null ? $dbm->getDefault() : $dbm->getOrDefault($role);
    }

    /**
     * Transaction run: BEGIN -> callback -> COMMIT; ROLLBACK on throw.
     */
    public static function transaction(callable $fn, ?string $role = null): mixed
    {
        $db = self::connection($role);
        $db->begin();

        try {
            $result = $fn($db);
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
}