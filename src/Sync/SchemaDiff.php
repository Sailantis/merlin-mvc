<?php
namespace Azera\Sync;

use Azera\Sync\Schema\TableSchema;

/**
 * Compares a PHP model definition against a database table schema and
 * produces SqlOperation objects that would bring the DB in line with
 * the model (PHP → DB direction).
 *
 * This is the inverse of ModelDiff, which goes DB → PHP.
 *
 * The diff is purely informational by default. Operations can be
 * converted to SQL via SqlGenerator and optionally executed.
 */
class SchemaDiff
{
    /**
     * @return SqlOperation[]
     */
    public function diff(ParsedModel $model, TableSchema $table): array
    {
        $ops = [];

        // Build maps keyed by column/property name
        $dbCols   = [];
        foreach ($table->columns as $col) {
            $dbCols[$col->name] = $col;
        }

        $phpProps = $model->properties;

        // 1. Columns present in PHP model but missing from DB → ADD COLUMN
        foreach ($phpProps as $name => $prop) {
            if (!isset($dbCols[$name])) {
                $op = new AddColumn();
                $op->table      = $table->name;
                $op->column     = $name;
                $op->type       = $this->mapPhpTypeToDb($prop->type);
                $op->nullable   = $this->isNullable($prop->type);
                $op->default    = null;
                $op->comment    = $this->extractCommentText($prop->docComment);
                $ops[] = $op;
            }
        }

        // 2. Columns present in DB but missing from PHP model → DROP COLUMN
        foreach ($dbCols as $name => $col) {
            if (!isset($phpProps[$name])) {
                $op = new DropColumn();
                $op->table  = $table->name;
                $op->column = $name;
                $ops[] = $op;
            }
        }

        // 3. Columns present in both but with type mismatch → ALTER COLUMN
        foreach ($phpProps as $name => $prop) {
            if (!isset($dbCols[$name])) {
                continue;
            }

            $col      = $dbCols[$name];
            $dbType   = $this->normalizeDbType($col->type);
            $phpType  = $this->mapPhpTypeToDb($prop->type);
            $expected = $this->normalizeDbType($phpType);

            // Compare normalized types (ignoring size/precision differences for simple types)
            if ($this->typesDiffer($dbType, $expected)) {
                $op = new AlterColumn();
                $op->table    = $table->name;
                $op->column   = $name;
                $op->oldType  = $col->type;
                $op->newType  = $phpType;
                $op->nullable = $this->isNullable($prop->type);
                $op->default  = $col->default;
                $ops[] = $op;
            }

            // Check nullable change
            $phpNullable = $this->isNullable($prop->type);
            if ($col->nullable !== $phpNullable && $dbType === $expected) {
                // Only add if not already an AlterColumn for this column
                $hasAlter = false;
                foreach ($ops as $existing) {
                    if ($existing instanceof AlterColumn && $existing->column === $name) {
                        $hasAlter = true;
                        break;
                    }
                }
                if (!$hasAlter) {
                    $op = new AlterColumn();
                    $op->table    = $table->name;
                    $op->column   = $name;
                    $op->oldType  = $col->type;
                    $op->newType  = $this->mapPhpTypeToDb($prop->type);
                    $op->nullable = $phpNullable;
                    $op->default  = $col->default;
                    $ops[] = $op;
                }
            }
        }

        // 4. Indexes present in DB but not corresponding to any PHP property → DROP INDEX
        //    (Index management from PHP annotations is deferred to v2)
        foreach ($table->indexes as $idx) {
            // Skip primary key indexes — those are managed by the column definition
            if ($this->isPrimaryKeyIndex($idx, $table)) {
                continue;
            }
            // For now, report indexes that exist but aren't tied to any property
            // This is informational — we don't auto-drop indexes
        }

        return $ops;
    }

    /**
     * Check if a table exists at all based on the diff.
     * If the model has no matching table, a CreateTable operation is needed.
     */
    public function diffTableExists(ParsedModel $model, ?TableSchema $table): array
    {
        if ($table === null) {
            // Table doesn't exist — generate CREATE TABLE + all columns
            $ops = [];
            $tableName = $this->resolveTableName($model);

            foreach ($model->properties as $name => $prop) {
                $op = new AddColumn();
                $op->table    = $tableName;
                $op->column   = $name;
                $op->type     = $this->mapPhpTypeToDb($prop->type);
                $op->nullable = $this->isNullable($prop->type);
                $op->default  = null;
                $op->comment  = $this->extractCommentText($prop->docComment);
                $ops[] = $op;
            }

            if (!empty($ops)) {
                // Wrap in a CreateTable operation
                $create = new CreateTable();
                $create->table   = $tableName;
                $create->columns = $ops;
                return [$create];
            }

            return [];
        }

        return $this->diff($model, $table);
    }

    // -------------------------------------------------------------------------
    //  Type mapping (PHP → DB)
    // -------------------------------------------------------------------------

    /**
     * Map a PHP type string to a MySQL/PostgreSQL/SQLite column type.
     *
     * @param string|null $phpType  PHP type (e.g. "int", "string", "?int", "float")
     */
    private function mapPhpTypeToDb(?string $phpType): string
    {
        if ($phpType === null) {
            return 'VARCHAR(255)';
        }

        // Strip nullable prefix
        $base = ltrim($phpType, '?');

        return match ($base) {
            'int', 'integer'    => 'INT',
            'bool', 'boolean'   => 'TINYINT(1)',
            'float', 'double',
            'real'              => 'DECIMAL(10,2)',
            'string'            => 'VARCHAR(255)',
            default             => 'VARCHAR(255)',
        };
    }

    /**
     * Check whether a PHP type declaration indicates nullable.
     */
    private function isNullable(?string $phpType): bool
    {
        if ($phpType === null) {
            return true; // untyped → assume nullable
        }
        return str_starts_with($phpType, '?');
    }

    // -------------------------------------------------------------------------
    //  Type comparison
    // -------------------------------------------------------------------------

    /**
     * Normalize a DB type string for comparison purposes.
     * Strips parenthesized sizes: "varchar(255)" → "varchar", "int(11)" → "int".
     */
    private function normalizeDbType(string $dbType): string
    {
        $normalized = strtolower(trim($dbType));
        // Remove size/precision: varchar(255) → varchar, int(11) → int
        $normalized = preg_replace('/\s*\([^)]*\)$/', '', $normalized);
        return $normalized;
    }

    /**
     * Check whether two normalized DB types are meaningfully different.
     */
    private function typesDiffer(string $current, string $expected): bool
    {
        // Normalize MySQL unsigned variants
        $current = str_replace(' unsigned', '', $current);
        $expected = str_replace(' unsigned', '', $expected);

        // Direct match after normalization
        if ($current === $expected) {
            return false;
        }

        // Alias groups: some types are functionally equivalent
        $aliases = [
            'int'        => ['int', 'integer', 'mediumint', 'smallint', 'tinyint'],
            'varchar'    => ['varchar', 'char', 'text', 'mediumtext', 'longtext', 'tinytext'],
            'decimal'    => ['decimal', 'numeric', 'float', 'double', 'real'],
            'bool'       => ['bool', 'boolean', 'tinyint'],
        ];

        foreach ($aliases as $group => $members) {
            if (in_array($current, $members, true) && in_array($expected, $members, true)) {
                return false;
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    private function isPrimaryKeyIndex($index, TableSchema $table): bool
    {
        // Primary key index typically named "PRIMARY" (MySQL) or "tablename_pkey" (PG)
        $name = strtolower($index->name);
        return $name === 'primary' || str_ends_with($name, '_pkey');
    }

    private function resolveTableName(ParsedModel $model): string
    {
        // Extract table name from class name: "User" → "users", "BlogPost" → "blog_posts"
        $classParts = explode('\\', $model->className);
        $shortName  = end($classParts);
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName)) . 's';
    }

    private function extractCommentText(?string $docComment): ?string
    {
        if ($docComment === null) {
            return null;
        }
        // Strip /** ... */ wrapper and leading " * "
        $cleaned = trim(preg_replace('/^\/\*\*|\*\/$/', '', $docComment));
        $cleaned = preg_replace('/^[ \t]*\*[ \t]?/m', '', $cleaned);
        // Take first line only
        $lines = explode("\n", $cleaned);
        $first = trim($lines[0]);
        return $first !== '' ? $first : null;
    }
}

// =============================================================================
//  SqlOperation hierarchy
// =============================================================================

abstract class SqlOperation
{
    public string $table;
}

class CreateTable extends SqlOperation
{
    /** @var AddColumn[] */
    public array $columns = [];
}

class AddColumn extends SqlOperation
{
    public string $column;
    public string $type;
    public bool $nullable = false;
    public mixed $default = null;
    public ?string $comment = null;
}

class DropColumn extends SqlOperation
{
    public string $column;
}

class AlterColumn extends SqlOperation
{
    public string $column;
    public string $oldType;
    public string $newType;
    public bool $nullable = false;
    public mixed $default = null;
}

class DropIndex extends SqlOperation
{
    public string $index;
}
