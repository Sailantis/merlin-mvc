<?php
namespace Azera\Sync\Schema;

use PDO;

class PostgresSchemaProvider implements SchemaProvider
{
    public function __construct(private PDO $pdo) {}

    /**
     * Lists tables, views, materialized views, and foreign tables.
     *
     * PostgreSQL system schemas (pg_catalog, pg_toast, information_schema, …)
     * are always excluded so that model tooling only sees user tables.
     */
    public function listTables(?string $schema = null): array
    {
        if ($schema === null) {
            $schema = $this->getCurrentSchemas();
        } else {
            $schema = [$schema];
        }

        // Filter out any system schemas regardless of how they got here.
        $system = [
            'pg_catalog',
            'pg_temp',
            'pg_toast',
            'pg_toast_temp',
            'information_schema'
        ];
        $schema = array_values(
            array_filter(
                $schema,
                static fn(string $s) => !in_array($s, $system, true)
            )
        );

        if ($schema === []) {
            return [];
        }

        $in = implode(',', array_fill(0, count($schema), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT n.nspname AS schema, c.relname AS name, c.relkind
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname IN ($in)
              AND c.relkind IN ('r','v','m','f')  -- table, view, matview, foreign table
            ORDER BY n.nspname, c.relname"
        );
        $stmt->execute($schema);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTableSchema(string $table, ?string $schema = null): TableSchema
    {
        [$schema, $relkind] = $this->resolveTable($table, $schema);

        $columns = $this->loadColumns($table, $schema);
        $indexes = $this->loadIndexes($table, $schema);
        $comment = $this->loadTableComment($table, $schema);

        return new TableSchema(
            $table,
            $comment,
            $columns,
            $indexes
        );
    }

    /**
     * Returns the schema that PostgreSQL would use based on the search_path.
     */
    private function resolveTable(string $table, ?string $schema): array
    {
        if ($schema !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT n.nspname, c.relkind
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE c.relname = ? AND n.nspname = ?
                LIMIT 1"
            );
            $stmt->execute([$table, $schema]);
        } else {
            // consider search_path
            $stmt = $this->pdo->prepare(
                "SELECT n.nspname, c.relkind
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE c.relname = ?
                ORDER BY n.nspname = ANY (current_schemas(true)) DESC
                LIMIT 1"
            );
            $stmt->execute([$table]);
        }

        $row = $stmt->fetch(PDO::FETCH_NUM);

        if (!$row) {
            throw new \RuntimeException("Table '$table' not found in any schema.");
        }

        return $row; // [schema, relkind]
    }

    private function loadTableComment(string $table, string $schema): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT obj_description(c.oid) AS comment
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE c.relname = ? AND n.nspname = ?"
        );
        $stmt->execute([$table, $schema]);

        $comment = $stmt->fetchColumn();
        return ($comment !== '' && $comment !== false) ? $comment : null;
    }

    private function loadColumns(string $table, string $schema): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                a.attname AS name,
                pg_catalog.format_type(a.atttypid, a.atttypmod) AS type,
                NOT a.attnotnull AS nullable,
                pg_get_expr(ad.adbin, ad.adrelid) AS default,
                col_description(a.attrelid, a.attnum) AS comment,
                ix.indisprimary AS primary
            FROM pg_attribute a
            JOIN pg_class c ON c.oid = a.attrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            LEFT JOIN pg_attrdef ad ON ad.adrelid = a.attrelid AND ad.adnum = a.attnum
            LEFT JOIN pg_index ix ON ix.indrelid = c.oid AND a.attnum = ANY(ix.indkey) AND ix.indisprimary
            WHERE c.relname = ? AND n.nspname = ? AND a.attnum > 0 AND NOT a.attisdropped
            ORDER BY a.attnum"
        );
        $stmt->execute([$table, $schema]);

        $cols = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $cols[] = new ColumnSchema(
                name: $col['name'],
                type: $col['type'],
                nullable: (bool) $col['nullable'],
                default: $col['default'],
                primary: (bool) $col['primary'],
                comment: $col['comment'] ?: null
            );
        }

        return $cols;
    }

    private function loadIndexes(string $table, string $schema): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                i.relname AS name,
                ix.indisunique AS unique,
                array_agg(a.attname ORDER BY x.ordinality) AS columns
            FROM pg_class t
            JOIN pg_namespace n ON n.oid = t.relnamespace
            JOIN pg_index ix ON ix.indrelid = t.oid
            JOIN pg_class i ON i.oid = ix.indexrelid
            JOIN LATERAL unnest(ix.indkey) WITH ORDINALITY AS x(attnum, ordinality) ON true
            LEFT JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = x.attnum
            WHERE n.nspname = ? AND t.relname = ?
            GROUP BY i.relname, ix.indisunique
            ORDER BY i.relname"
        );
        $stmt->execute([$schema, $table]);

        $indexes = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns = $row['columns'] ?? '';
            // PostgreSQL array_agg returns a string like {col1,col2};
            // parse it into a PHP array.
            if (is_string($columns)) {
                $columns = trim($columns, '{}');
                $columns = $columns === '' ? [] : explode(',', $columns);
            }
            $indexes[] = new IndexSchema(
                name: $row['name'],
                unique: (bool) $row['unique'],
                columns: $columns
            );
        }

        return $indexes;
    }

    /**
     * Returns the user schemas from the current search_path, excluding
     * PostgreSQL's implicit system schemas (pg_catalog, pg_temp, pg_toast,
     * information_schema) so that model:list --missing does not report
     * hundreds of internal catalog tables.
     */
    private function getCurrentSchemas(): array
    {
        // current_schemas(false) omits implicitly-added schemas such as
        // pg_catalog and pg_temp that are always present on the search_path.
        $stmt    = $this->pdo->query("SELECT unnest(current_schemas(false))");
        $schemas = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Belt-and-suspenders: drop any system schema that happens to be
        // listed explicitly on the search_path as well.
        $system = [
            'pg_catalog',
            'pg_temp',
            'pg_toast',
            'pg_toast_temp',
            'information_schema'
        ];

        return array_values(
            array_filter(
                $schemas,
                static fn(string $s) => !in_array($s, $system, true)
            )
        );
    }
}