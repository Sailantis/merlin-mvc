<?php
namespace Azera\Sync;

use Azera\Sync\Schema\TableSchema;

class ModelDiff
{
    /**
     * @return DiffOperation[]
     */
    public function diff(TableSchema $table, ParsedModel $model, ?SyncOptions $options = null): array
    {
        $ops = [];

        $generateAccessors = $options?->generateAccessors ?? false;
        $fieldVisibility = $options?->fieldVisibility ?? 'public';
        $deprecate = $options?->deprecate ?? true;

        // Build DB column map, skipping underscore-prefixed column names
        $dbCols = [];
        foreach ($table->columns as $col) {
            if (str_starts_with($col->name, '_')) {
                continue;
            }
            $dbCols[$col->name] = $col;
        }

        // Properties are already name-keyed in ParsedModel
        $phpProps = $model->properties;

        // 1. Missing properties (DB → PHP)
        foreach ($dbCols as $name => $col) {
            if (!isset($phpProps[$name])) {
                $op = new AddProperty();
                $op->property = $name;
                $op->type = $this->mapColumnToPhpType($col);
                $op->nullable = $col->nullable && !$col->primary;
                $op->comment = $col->comment;
                $op->visibility = $fieldVisibility;
                $ops[] = $op;

                if ($generateAccessors) {
                    $acc = new AddAccessor();
                    $acc->property = $name;
                    $acc->phpType = $op->type;
                    $acc->methodName = $this->camelize($name);
                    $acc->visibility = $fieldVisibility;
                    $ops[] = $acc;
                }
            }
        }

        // 2. Orphaned properties (PHP → DB): optionally mark @deprecated
        //    Properties starting with '_' are ignored entirely.
        foreach ($phpProps as $name => $prop) {
            if (str_starts_with($name, '_')) {
                continue;
            }
            if (!isset($dbCols[$name])) {
                if (!$deprecate) {
                    continue;
                }
                $op = new RemoveProperty();
                $op->property = $name;
                $ops[] = $op;
            }
        }

        // 3. Type changes
        foreach ($dbCols as $name => $col) {
            if (isset($phpProps[$name])) {
                $phpType = $phpProps[$name]->type;
                $dbType = $this->mapColumnToPhpType($col);

                if ($phpType !== $dbType) {
                    $op = new UpdatePropertyType();
                    $op->property = $name;
                    $op->oldType = $phpType;
                    $op->newType = $dbType;
                    $ops[] = $op;
                }
            }
        }

        // 4. Comment changes (only when DB provides a column comment)
        foreach ($dbCols as $name => $col) {
            if (isset($phpProps[$name]) && $col->comment !== null) {
                $phpComment = $phpProps[$name]->docComment;
                $dbComment = $col->comment;

                if ($this->normalizeComment($phpComment) !== $this->normalizeComment($dbComment)) {
                    $op = new UpdatePropertyComment();
                    $op->property = $name;
                    $op->oldComment = $phpComment;
                    $op->newComment = $dbComment;
                    $ops[] = $op;
                }
            }
        }

        // 5. Table comment → class PHPDoc (only when table has a comment)
        if (
            $table->comment !== null &&
            $this->normalizeComment($model->classComment) !== $this->normalizeComment($table->comment)
        ) {
            $op = new UpdateClassComment();
            $op->oldComment = $model->classComment;
            $op->newComment = $table->comment;
            $ops[] = $op;
        }

        return $ops;
    }

    private function mapColumnToPhpType(\Azera\Sync\Schema\ColumnSchema $col): string
    {
        $base = $this->mapDbTypeToPhp($col->type);
        return ($col->nullable && !$col->primary) ? '?' . $base : $base;
    }

    private function mapDbTypeToPhp(string $dbType): string
    {
        $dbType = strtolower($dbType);

        return match (true) {
            str_contains($dbType, 'int') => 'int',
            str_contains($dbType, 'bool') => 'bool',
            str_contains($dbType, 'decimal'),
            str_contains($dbType, 'numeric'),
            str_contains($dbType, 'float'),
            str_contains($dbType, 'double'),
            str_contains($dbType, 'real') => 'float',
            str_contains($dbType, 'char'),
            str_contains($dbType, 'text'),
            str_contains($dbType, 'varchar'),
            str_contains($dbType, 'date'),
            str_contains($dbType, 'time'),
            str_contains($dbType, 'json'),
            str_contains($dbType, 'uuid'),
            str_contains($dbType, 'enum') => 'string',
            default => 'string',
        };
    }

    private function normalizeComment(?string $comment): string
    {
        return trim((string) $comment);
    }

    private function camelize(string $name): string
    {
        return lcfirst(str_replace('_', '', ucwords($name, '_')));
    }
}

abstract class DiffOperation
{
    public string $property;
}

class AddProperty extends DiffOperation
{
    public string $type;
    public bool $nullable = false;
    public ?string $comment;
    /** Visibility modifier: 'public', 'protected', or 'private' */
    public string $visibility = 'public';
}

class RemoveProperty extends DiffOperation
{
    public bool $markDeprecated = true;
}

class AddAccessor extends DiffOperation
{
    /** PHP type string including nullability, e.g. 'string', '?int' */
    public string $phpType;
    /** Base camelized name used to build getX() / setX() accessors, e.g. 'userId' → getUserId() / setUserId() */
    public string $methodName;
    /** Visibility modifier: 'public', 'protected', or 'private' */
    public string $visibility = 'public';
}

class UpdatePropertyType extends DiffOperation
{
    public string $oldType;
    public string $newType;
}

class UpdatePropertyComment extends DiffOperation
{
    public ?string $oldComment;
    public ?string $newComment;
}

class UpdateClassComment extends DiffOperation
{
    public ?string $oldComment;
    public ?string $newComment;
}
