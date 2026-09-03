<?php

namespace Azera\Sync\Schema;

use Azera\Db\Database;
use RuntimeException;

/**
 * Factory for creating the correct {@see SchemaProvider} for a given
 * SQL connection based on its driver name.
 *
 * Shared by the model-sync machinery ({@see \Azera\Sync\SyncRunner}) and
 * the CLI Database inspection task ({@see \Azera\Cli\Tasks\DbTask}) so
 * that driverâ†’provider mapping lives in exactly one place.
 */
final class SchemaProviderFactory
{
    /**
     * Create a SchemaProvider for the given SQL connection.
     *
     * @param Database $db A connected Database instance.
     * @return SchemaProvider The provider matching the connection's driver.
     * @throws RuntimeException If the driver has no registered provider.
     */
    public static function create(Database $db): SchemaProvider
    {
        $pdo = $db->getInternalConnection();

        return match ($db->getDriver()) {
            'mysql'  => new MySqlSchemaProvider($pdo),
            'pgsql'  => new PostgresSchemaProvider($pdo),
            'sqlite' => new SqliteSchemaProvider($pdo),
            default  => throw new RuntimeException(
                "No schema provider available for driver '{$db->getDriver()}'"
            )
        };
    }
}


