<?php

namespace Azera\Facade;

use Azera\AppContext;

/**
 * Thin static proxy for transactions over the SQL connection registry.
 *
 * Plain static methods; no magic. Complements Db::transaction() with
 * explicit begin/commit/rollback handles when the callback shape doesn't
 * fit.
 */
final class Tx
{
    public static function begin(?string $role = null): void
    {
        self::connection($role)->begin();
    }

    public static function commit(?string $role = null): void
    {
        self::connection($role)->commit();
    }

    public static function rollback(?string $role = null): void
    {
        self::connection($role)->rollback();
    }

    public static function level(?string $role = null): bool
    {
        return self::connection($role)->inTransaction();
    }

    private static function connection(?string $role): \Azera\Db\Database
    {
        $dbm = AppContext::instance()->dbManager();

        return $role === null ? $dbm->getDefault() : $dbm->getOrDefault($role);
    }
}