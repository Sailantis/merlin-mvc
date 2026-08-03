<?php
namespace Azera\Sync;

/**
 * Converts SqlOperation objects into executable SQL strings.
 *
 * Supports MySQL, PostgreSQL, and SQLite with driver-specific syntax
 * for each operation type.
 */
class SqlGenerator
{
    /**
     * Generate SQL statements from an array of operations.
     *
     * @param SqlOperation[] $operations  The diff operations to convert
     * @param string         $driver      Database driver name: "mysql", "pgsql", or "sqlite"
     * @return string[]                   SQL statements, one per operation
     */
    public function generate(array $operations, string $driver): array
    {
        $sql = [];

        foreach ($operations as $op) {
            $statements = match (true) {
                $op instanceof CreateTable => $this->createTable($op, $driver),
                $op instanceof AddColumn   => $this->addColumn($op, $driver),
                $op instanceof DropColumn  => $this->dropColumn($op, $driver),
                $op instanceof AlterColumn => $this->alterColumn($op, $driver),
                $op instanceof DropIndex   => $this->dropIndex($op, $driver),
                default                    => []
            };

            $sql = array_merge($sql, $statements);
        }

        return $sql;
    }

    // -------------------------------------------------------------------------
    //  CREATE TABLE
    // -------------------------------------------------------------------------

    private function createTable(CreateTable $op, string $driver): array
    {
        $lines = [];
        foreach ($op->columns as $col) {
            $lines[] = '    ' . $this->formatColumnDef($col, $driver);
        }

        if (empty($lines)) {
            return [];
        }

        $body        = implode(",\n", $lines);
        $quotedTable = $this->quoteIdentifier($op->table, $driver);

        return ["CREATE TABLE {$quotedTable}\n(\n{$body}\n);"];
    }

    // -------------------------------------------------------------------------
    //  ADD COLUMN
    // -------------------------------------------------------------------------

    private function addColumn(AddColumn $op, string $driver): array
    {
        $quotedTable  = $this->quoteIdentifier($op->table, $driver);
        $quotedColumn = $this->quoteIdentifier($op->column, $driver);
        $typeDef      = $this->typeDefinition($op->type, $op->nullable, $driver);

        $sql = "ALTER TABLE {$quotedTable} ADD COLUMN {$quotedColumn} {$typeDef}";

        if ($op->default !== null) {
            $sql .= ' DEFAULT ' . $this->formatDefault($op->default, $op->type, $driver);
        }

        if (!$op->nullable) {
            $sql .= ' NOT NULL';
        }

        $sql .= ';';

        $statements = [$sql];

        // MySQL supports inline COMMENT; PostgreSQL uses separate COMMENT ON
        if ($op->comment !== null) {
            if ($driver === 'mysql') {
                $statements[0] = rtrim($sql, ';') . ' COMMENT ' . $this->quoteString($op->comment, $driver) . ';';
            } elseif ($driver === 'pgsql') {
                $statements[] = "COMMENT ON COLUMN {$quotedTable}.{$quotedColumn} IS " . $this->quoteString($op->comment, $driver) . ';';
            }
        }

        return $statements;
    }

    // -------------------------------------------------------------------------
    //  DROP COLUMN
    // -------------------------------------------------------------------------

    private function dropColumn(DropColumn $op, string $driver): array
    {
        $quotedTable  = $this->quoteIdentifier($op->table, $driver);
        $quotedColumn = $this->quoteIdentifier($op->column, $driver);

        return ["ALTER TABLE {$quotedTable} DROP COLUMN {$quotedColumn};"];
    }

    // -------------------------------------------------------------------------
    //  ALTER COLUMN
    // -------------------------------------------------------------------------

    private function alterColumn(AlterColumn $op, string $driver): array
    {
        $quotedTable  = $this->quoteIdentifier($op->table, $driver);
        $quotedColumn = $this->quoteIdentifier($op->column, $driver);
        $typeDef      = $this->typeDefinition($op->newType, $op->nullable, $driver);

        return match ($driver) {
            'mysql'  => $this->alterColumnMysql($quotedTable, $quotedColumn, $typeDef, $op),
            'pgsql'  => $this->alterColumnPostgres($quotedTable, $quotedColumn, $typeDef, $op),
            'sqlite' => $this->alterColumnSqlite($op, $driver),
            default  => []
        };
    }

    private function alterColumnMysql(string $quotedTable, string $quotedColumn, string $typeDef, AlterColumn $op): array
    {
        $sql = "ALTER TABLE {$quotedTable} MODIFY COLUMN {$quotedColumn} {$typeDef}";

        if (!$op->nullable) {
            $sql .= ' NOT NULL';
        }

        if ($op->default !== null) {
            $sql .= ' DEFAULT ' . $this->formatDefault($op->default, $op->newType, 'mysql');
        }

        return [$sql . ';'];
    }

    private function alterColumnPostgres(string $quotedTable, string $quotedColumn, string $typeDef, AlterColumn $op): array
    {
        $statements = [];

        // PostgreSQL splits type change and nullability into separate statements
        // Extract the base type (without NULL/NOT NULL) for ALTER COLUMN TYPE
        $baseType     = $this->extractBaseType($op->newType, 'pgsql');
        $statements[] = "ALTER TABLE {$quotedTable} ALTER COLUMN {$quotedColumn} TYPE {$baseType};";

        if ($op->nullable) {
            $statements[] = "ALTER TABLE {$quotedTable} ALTER COLUMN {$quotedColumn} DROP NOT NULL;";
        } else {
            $statements[] = "ALTER TABLE {$quotedTable} ALTER COLUMN {$quotedColumn} SET NOT NULL;";
        }

        if ($op->default !== null) {
            $statements[] = "ALTER TABLE {$quotedTable} ALTER COLUMN {$quotedColumn} SET DEFAULT " . $this->formatDefault($op->default, $op->newType, 'pgsql') . ';';
        }

        return $statements;
    }

    private function alterColumnSqlite(AlterColumn $op, string $driver): array
    {
        // SQLite has very limited ALTER TABLE support:
        // - Cannot ALTER COLUMN type directly
        // - Cannot DROP COLUMN in older versions (< 3.35.0)
        // For type changes, we emit a comment explaining the limitation
        // and provide the recreation SQL.
        $quotedTable = $this->quoteIdentifier($op->table, $driver);
        return [
            "-- SQLite: ALTER COLUMN requires table recreation.",
            "-- Backup data, drop table, create new table with updated schema, restore data.",
            "-- Consider using a migration tool for production SQLite databases.",
            "-- ALTER TABLE {$quotedTable} ALTER COLUMN {$this->quoteIdentifier($op->column, $driver)} TYPE {$this->extractBaseType($op->newType, 'sqlite')};"
        ];
    }

    // -------------------------------------------------------------------------
    //  DROP INDEX
    // -------------------------------------------------------------------------

    private function dropIndex(DropIndex $op, string $driver): array
    {
        return match ($driver) {
            'mysql'  => ["DROP INDEX `{$op->index}` ON `{$op->table}`;"],
            'pgsql'  => ["DROP INDEX IF EXISTS \"{$op->index}\";"],
            'sqlite' => ["DROP INDEX IF EXISTS \"{$op->index}\";"],
            default  => []
        };
    }

    // -------------------------------------------------------------------------
    //  Column definition formatting
    // -------------------------------------------------------------------------

    private function formatColumnDef(AddColumn $op, string $driver): string
    {
        $quotedColumn = $this->quoteIdentifier($op->column, $driver);
        $typeDef      = $this->typeDefinition($op->type, $op->nullable, $driver);

        $parts = [$quotedColumn, $typeDef];

        if (!$op->nullable) {
            $parts[] = 'NOT NULL';
        }

        if ($op->default !== null) {
            $parts[] = 'DEFAULT ' . $this->formatDefault($op->default, $op->type, $driver);
        }

        if ($driver === 'mysql' && $op->comment !== null) {
            $parts[] = 'COMMENT ' . $this->quoteString($op->comment, $driver);
        }

        return implode(' ', $parts);
    }

    /**
     * Build a full column type definition string including NULL/NOT NULL.
     * @param string $type
     * @param bool $nullable
     * @param string $driver
     */
    private function typeDefinition(string $type, bool $nullable, string $driver): string
    {
        return $this->normalizeTypeForDriver($type, $driver);
    }

    /**
     * Extract the base type without NULL/NOT NULL for PostgreSQL ALTER statements.
     * @param string $type
     * @param string $driver
     */
    private function extractBaseType(string $type, string $driver): string
    {
        return $this->normalizeTypeForDriver($type, $driver);
    }

    /**
     * Normalize a PHP-mapped type string for the specific driver.
     * @param string $type
     * @param string $driver
     */
    private function normalizeTypeForDriver(string $type, string $driver): string
    {
        $upper = strtoupper(trim($type));

        return match ($driver) {
            'mysql' => match (true) {
                $upper === 'INT' || $upper === 'INTEGER'                       => 'INT',
                $upper === 'BOOL' || $upper === 'BOOLEAN'                      => 'TINYINT(1)',
                $upper === 'FLOAT' || $upper === 'DOUBLE' || $upper === 'REAL' => 'DECIMAL(10,2)',
                default                                                        => $type
            },
            'pgsql' => match (true) {
                $upper === 'INT' || $upper === 'INTEGER'                       => 'INTEGER',
                $upper === 'BOOL' || $upper === 'BOOLEAN'                      => 'BOOLEAN',
                $upper === 'TINYINT(1)'                                        => 'BOOLEAN',
                $upper === 'FLOAT' || $upper === 'DOUBLE' || $upper === 'REAL' => 'NUMERIC(10,2)',
                $upper === 'VARCHAR(255)' || $upper === 'VARCHAR'              => 'VARCHAR(255)',
                default                                                        => $type
            },
            'sqlite' => match (true) {
                $upper === 'INT' || $upper === 'INTEGER'                                                     => 'INTEGER',
                $upper === 'BOOL' || $upper === 'BOOLEAN' || $upper === 'TINYINT(1)'                         => 'INTEGER',
                $upper === 'FLOAT' || $upper === 'DOUBLE' || $upper === 'DECIMAL(10,2)' || $upper === 'REAL' => 'REAL',
                $upper === 'VARCHAR(255)' || $upper === 'VARCHAR'                                            => 'TEXT',
                default                                                                                      => $type
            },
            default => $type
        };
    }

    // -------------------------------------------------------------------------
    //  Quoting helpers
    // -------------------------------------------------------------------------

    private function quoteIdentifier(string $name, string $driver): string
    {
        return match ($driver) {
            'mysql'  => "`{$name}`",
            'pgsql'  => "\"{$name}\"",
            'sqlite' => "\"{$name}\"",
            default  => "`{$name}`"
        };
    }

    private function quoteString(string $value, string $driver): string
    {
        $escaped = addslashes($value);
        return "'{$escaped}'";
    }

    private function formatDefault(mixed $default, string $type, string $driver): string
    {
        if ($default === null) {
            return 'NULL';
        }

        $upper = strtoupper(trim($type));

        // Numeric types: no quotes
        if (
            in_array($upper, [
                'INT',
                'INTEGER',
                'TINYINT(1)',
                'BIGINT',
                'SMALLINT',
                'MEDIUMINT',
                'FLOAT',
                'DOUBLE',
                'DECIMAL',
                'DECIMAL(10,2)',
                'REAL',
                'NUMERIC',
                'NUMERIC(10,2)'
            ], true)
        ) {
            return (string) $default;
        }

        // Boolean: convert to 0/1 for MySQL, true/false for PG
        if (in_array($upper, ['BOOL', 'BOOLEAN'], true)) {
            return $driver === 'pgsql'
                ? (filter_var($default, FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE')
                : (filter_var($default, FILTER_VALIDATE_BOOLEAN) ? '1' : '0');
        }

        // String types: quote
        if ($default === 'CURRENT_TIMESTAMP') {
            return 'CURRENT_TIMESTAMP';
        }

        return $this->quoteString((string) $default, $driver);
    }
}