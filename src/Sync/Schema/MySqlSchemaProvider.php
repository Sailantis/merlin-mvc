<?php
namespace Azera\Sync\Schema;

use PDO;

class MySqlSchemaProvider implements SchemaProvider
{
    public function __construct(private PDO $pdo) {}

    public function listTables(?string $schema = null): array
    {
        $stmt = $this->pdo->query(
            "SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE()"
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTableSchema(string $table, ?string $schema = null): TableSchema
    {
        $columns = $this->loadColumns($table);
        $indexes = $this->loadIndexes($table);
        $comment = $this->loadTableComment($table);

        return new TableSchema(
            $table,
            $comment,
            $columns,
            $indexes
        );
    }

    private function loadTableComment(string $table): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT TABLE_COMMENT
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);

        $comment = $stmt->fetchColumn();
        return $comment !== '' ? $comment : null;
    }

    private function loadColumns(string $table): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, COLUMN_COMMENT
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION"
        );
        $stmt->execute([$table]);

        $result = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $result[] = new ColumnSchema(
                name: $col['COLUMN_NAME'],
                type: $col['COLUMN_TYPE'],
                nullable: $col['IS_NULLABLE'] === 'YES',
                default: $col['COLUMN_DEFAULT'],
                primary: $col['COLUMN_KEY'] === 'PRI',
                comment: $col['COLUMN_COMMENT'] ?: null
            );
        }

        return $result;
    }

    private function loadIndexes(string $table): array
    {
        $stmt = $this->pdo->prepare("SHOW INDEX FROM `$table`");
        $stmt->execute();

        $indexes = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = $row['Key_name'];

            if (!isset($indexes[$name])) {
                $indexes[$name] = new IndexSchema(
                    name: $name,
                    unique: !$row['Non_unique'],
                    columns: []
                );
            }

            $indexes[$name]->columns[] = $row['Column_name'];
        }

        return array_values($indexes);
    }
}