<?php

namespace Azera\Cli\Tasks;

use Azera\AppContext;
use Azera\Cli\Task;
use Azera\Db\Database;
use Azera\Sync\Schema\SchemaProviderFactory;

/**
 * Inspect the database schema and run raw SQL queries.
 *
 * Usage:
 *   db:tables  [--database=<role>] [--schema=<name>] [--with-counts]
 *   db:table  <table>  [--database=<role>] [--schema=<name>]
 *   db:query   <sql>    [--database=<role>] [--force] [--file=<path>]
 *
 * Options:
 *   --database=<role>   Database role to use (default: "default")
 *   --schema=<name>     Database schema to list tables from (PostgreSQL only)
 *   --with-counts       (tables) Also show approximate row counts
 *   --force             (query) Allow destructive queries (DROP/TRUNCATE/DELETE/ALTER)
 *   --file=<path>       (query) Read SQL from a file instead of an argument
 *
 * Examples:
 *   db:tables
 *   db:tables --with-counts
 *   db:table users
 *   db:query "SELECT * FROM users LIMIT 5"
 *   db:query --file=migration.sql --force
 */
class DbTask extends Task
{
    /**
     * List all tables in the database.
     */
    public function tablesAction(): void
    {
        $db       = $this->resolveDb();
        $provider = SchemaProviderFactory::create($db);
        $schema   = $this->option('schema');

        // For PostgreSQL, if no --schema is given, list tables from all
        // user schemas (not just search_path). For MySQL/SQLite the
        // --schema option is ignored.
        if ($schema === null && $db->getDriver() === 'pgsql') {
            $rawTables = $this->listAllPostgresTables($db);
        } else {
            $rawTables = $provider->listTables($schema);
        }

        if (empty($rawTables)) {
            $this->muted('No tables found.');
            return;
        }

        // Normalise: MySQL/SQLite return flat strings; PostgreSQL returns
        // associative rows with 'schema' and 'name' keys.
        $tables = array_map(
            static fn($t) => is_array($t)
                ? [($t['schema'] ?? null), ($t['name'] ?? reset($t))]
                : [null, $t],
            $rawTables
        );

        $withCounts = isset($this->options['with-counts']);
        $headers    = $withCounts ? ['Schema', 'Table', 'Rows'] : ['Schema', 'Table'];
        $rows       = [];

        foreach ($tables as [$tblSchema, $table]) {
            $schemaLabel = $tblSchema ?? '';
            $tableLabel  = $this->style($table, 'cyan');

            if ($withCounts) {
                try {
                    $quoted = $tblSchema !== null
                        ? $db->quoteIdentifier($tblSchema, $table)
                        : $db->quoteIdentifier($table);
                    $count  = $db->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
                    $rows[] = [$schemaLabel, $tableLabel, (string) $count];
                } catch (\Throwable) {
                    $rows[] = [$schemaLabel, $tableLabel, $this->style('error', 'bred')];
                }
            } else {
                $rows[] = [$schemaLabel, $tableLabel];
            }
        }

        $this->console->printTable($headers, $rows);
        $this->muted("\n" . count($tables) . ' table(s).');
    }

    /**
     * Show column details for a specific table (db:table).
     */
    public function tableAction(string $table = ''): void
    {
        if ($table === '') {
            $this->error('Please specify a table name: azera db:table <table>');
            return;
        }

        $db       = $this->resolveDb();
        $provider = SchemaProviderFactory::create($db);
        $schema   = strchr($table, '.', true);
        if ($schema !== false) {
            $table = substr($table, strlen($schema) + 1);
        } else {
            $schema = $this->option('schema');
        }

        try {
            $schemaResult = $provider->getTableSchema($table, $schema);
        } catch (\Throwable $e) {
            $this->error("Failed to get schema for '{$table}': {$e->getMessage()}");
            return;
        }

        // Table header
        $this->line('');
        $this->writeln($this->style(" Table: {$schemaResult->name} ", 'bold', 'bg-blue'));
        if ($schemaResult->comment) {
            $this->muted("  {$schemaResult->comment}");
        }
        $this->line('');

        // Columns
        $headers = ['Column', 'Type', 'Nullable', 'Default', 'Primary', 'Comment'];
        $rows    = [];

        foreach ($schemaResult->columns as $col) {
            $rows[] = [
                $this->style($col->name, 'cyan'),
                $col->type,
                $col->nullable ? $this->style('YES', 'byellow') : 'NO',
                $col->default !== null ? (string) $col->default : $this->style('NULL', 'gray'),
                $col->primary ? $this->style('PRI', 'bgreen') : '',
                $col->comment ?? '',
            ];
        }

        $this->console->printTable($headers, $rows);

        // Indexes
        if (!empty($schemaResult->indexes)) {
            $this->line('');
            $this->writeln($this->style(' Indexes ', 'bold', 'bg-blue'));
            $this->line('');

            $idxHeaders = ['Name', 'Unique', 'Columns'];
            $idxRows    = [];

            foreach ($schemaResult->indexes as $idx) {
                $idxRows[] = [
                    $this->style($idx->name, 'cyan'),
                    $idx->unique ? $this->style('UNIQUE', 'bgreen') : '',
                    implode(', ', $idx->columns),
                ];
            }

            $this->console->printTable($idxHeaders, $idxRows);
        }
    }

    /**
     * Execute a raw SQL query and display results.
     */
    public function queryAction(string $sql = ''): void
    {
        // Read from --file if specified
        $file = $this->option('file');
        if ($file !== null) {
            if (!is_file($file)) {
                $this->error("File not found: {$file}");
                return;
            }
            $sql = file_get_contents($file);
        }

        if ($sql === '') {
            $this->error('Please provide a SQL query: azera db:query "<sql>" or --file=<path>');
            return;
        }

        // Safety check for destructive queries
        $sqlUpper      = ltrim(strtoupper($sql));
        $destructive   = ['DROP', 'TRUNCATE', 'DELETE', 'ALTER'];
        $isDestructive = false;
        foreach ($destructive as $kw) {
            if (str_starts_with($sqlUpper, $kw)) {
                $isDestructive = true;
                break;
            }
        }

        if ($isDestructive && !isset($this->options['force'])) {
            $this->error('This query appears to be destructive. Use --force to confirm.');
            $this->muted('  Query: ' . substr($sql, 0, 200));
            return;
        }

        $db = $this->resolveDb();

        try {
            $result = $db->query($sql);
        } catch (\Throwable $e) {
            $this->error("Query failed: {$e->getMessage()}");
            return;
        }

        if ($result === true) {
            // Non-SELECT query
            $affected = $db->rowCount();
            $this->success("Query executed successfully. {$affected} row(s) affected.");
            return;
        }

        // SELECT query — fetch all rows
        $rows = $result->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rows)) {
            $this->muted('Query returned no rows.');
            return;
        }

        // Build table from result set
        $headers   = array_keys($rows[0]);
        $tableRows = [];

        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $h) {
                $val     = $row[$h];
                $cells[] = $val === null ? $this->style('NULL', 'gray') : (string) $val;
            }
            $tableRows[] = $cells;
        }

        $this->console->printTable($headers, $tableRows);
        $this->muted("\n" . count($rows) . ' row(s).');
    }

    // -----------------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------------

    private function resolveDb(): Database
    {
        $role = (string) $this->option('database', 'default');
        $ctx  = AppContext::instance();
        return $ctx->dbManager()->getOrDefault($role);
    }

    /**
     * List all tables across all user schemas in PostgreSQL (not just
     * those on the current search_path).
     */
    private function listAllPostgresTables(Database $db): array
    {
        $pdo  = $db->getInternalConnection();
        $stmt = $pdo->prepare(
            "SELECT n.nspname AS schema, c.relname AS name
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname NOT IN ('pg_catalog', 'pg_toast', 'information_schema')
              AND n.nspname NOT LIKE 'pg_toast%'
              AND c.relkind IN ('r','v','m','f')
            ORDER BY n.nspname, c.relname"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

}