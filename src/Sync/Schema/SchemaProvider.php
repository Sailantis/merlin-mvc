<?php
namespace Azera\Sync\Schema;

interface SchemaProvider
{
    /**
     * @param  string|null $schema  Database schema to scan (used by PostgreSQL; ignored by MySQL/SQLite).
     *                              When null the provider falls back to its engine default.
     * @return string[]
     */
    public function listTables(?string $schema = null): array;

    /**
     * @param  string|null $schema  Database schema (used by PostgreSQL; ignored by MySQL/SQLite).
     *                              When null the provider falls back to its engine default.
     * @return TableSchema
     */
    public function getTableSchema(string $table, ?string $schema = null): TableSchema;
}

class TableSchema
{
    public function __construct(
        public string $name,
        public ?string $comment,
        /** @var ColumnSchema[] */
        public array $columns,
        /** @var IndexSchema[] */
        public array $indexes
    ) {
    }
}

class ColumnSchema
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
        public mixed $default,
        public bool $primary,
        public ?string $comment
    ) {
    }
}

class IndexSchema
{
    public function __construct(
        public string $name,
        public bool $unique,
        /** @var string[] */
        public array $columns
    ) {
    }
}
