<?php
namespace Azera\Sync;

use Azera\Db\Database;
use Azera\Db\DatabaseManager;
use Azera\Sync\Schema\SchemaProvider;
use Azera\Sync\Schema\SchemaProviderFactory;
use RuntimeException;

class SyncRunner
{
    private ModelDiff $diff;
    private CodeGenerator $generator;

    public function __construct(
        private DatabaseManager $dbManager
    )
    {
        $this->diff      = new ModelDiff();
        $this->generator = new CodeGenerator();
    }

    // -------------------------------------------------------------------------
    //  Public API
    // -------------------------------------------------------------------------

    /**
     * Synchronise a single model file against the Database schema.
     *
     * @param string $filePath Absolute path to the model PHP file
     * @param bool   $dryRun   When true the file is NOT written; changes are only calculated
     * @param string $dbRole   SQL role to introspect (falls back to default if not registered)
     */
    public function syncModel(string $filePath, bool $dryRun = false, string $dbRole = 'read', ?SyncOptions $options = null): SyncResult
    {
        try {
            // 1. Parse the model file
            $parser = new ModelParser($filePath);
            $parsed = $parser->parse();

            // 2. Resolve the table name and optional DB schema from the model
            [$tableName, $schema] = $this->resolveModelInfo($parsed->className);

            // 3. Get the SQL connection for the requested role
            $db = $this->dbManager->getOrDefault($dbRole);

            // 4. Build schema provider from the connection's driver
            $provider = $this->buildProvider($db);

            // 5. Fetch table schema (schema param used by PostgreSQL, ignored by others)
            $tableSchema = $provider->getTableSchema($tableName, $schema);

            // 6. Calculate diff
            $ops = $this->diff->diff($tableSchema, $parsed, $options);

            if (empty($ops)) {
                return new SyncResult(
                    filePath: $filePath,
                    className: $parsed->className,
                    tableName: $tableName,
                    operations: [],
                    applied: false
                );
            }

            // 7. Apply or return dry-run result
            if (!$dryRun) {
                $newCode = $this->generator->applyDiff($parsed, $ops);
                file_put_contents($filePath, $newCode);
            }

            return new SyncResult(
                filePath: $filePath,
                className: $parsed->className,
                tableName: $tableName,
                operations: $ops,
                applied: !$dryRun
            );

        } catch (\Throwable $e) {
            return new SyncResult(
                filePath: $filePath,
                className: $filePath,
                tableName: '?',
                operations: [],
                applied: false,
                error: $e->getMessage()
            );
        }
    }

    /**
     * Synchronise multiple model files.
     *
     * @param string[] $modelFiles Absolute paths to model PHP files
     * @param bool     $dryRun
     * @param string   $dbRole
     * @return SyncResult[]
     */
    public function syncAll(array $modelFiles, bool $dryRun = false, string $dbRole = 'read', ?SyncOptions $options = null): array
    {
        $results = [];
        foreach ($modelFiles as $file) {
            $results[] = $this->syncModel($file, $dryRun, $dbRole, $options);
        }
        return $results;
    }

    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    /**
     * Return all table names in the Database for the given role and optional schema.
     *
     * @param  string|null $schema  DB schema to scan (PostgreSQL only; pass null to use server default).
     * @return string[]
     */
    public function listDatabaseTables(string $dbRole = 'read', ?string $schema = null): array
    {
        $db = $this->dbManager->getOrDefault($dbRole);
        return $this->buildProvider($db)->listTables($schema);
    }

    /**
     * Scaffold a new model file. Throws if the file already exists.
     *
     * The generated class includes an explicit source() override so the
     * table name is always unambiguous to subsequent sync operations.
     * If $schema is given, a schema() override is also generated.
     */
    public function createModelFile(
        string $filePath,
        string $namespace,
        string $className,
        string $tableName,
        ?string $schema = null
    ): void
    {
        if (file_exists($filePath)) {
            throw new RuntimeException("File already exists: {$filePath}");
        }

        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $schemaMethod = $schema !== null
            ? "\n    public function schema(): ?string { return '{$schema}'; }\n"
            : '';

        $content = <<<PHP
<?php

namespace {$namespace};

use Azera\Core\Model;

class {$className} extends Model
{
    public function source(): string { return '{$tableName}'; }{$schemaMethod}
    // Properties will be added automatically by the sync task.
}
PHP;

        file_put_contents($filePath, $content);
    }

    /**
     * Resolve the table name for a model file without calculating a full diff.
     * Returns null if the file cannot be parsed or the class is not a valid Model.
     */
    public function getModelTableName(string $filePath): ?string
    {
        try {
            $parser = new ModelParser($filePath);
            $parsed = $parser->parse();
            [$table] = $this->resolveModelInfo($parsed->className);
            return $table;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve both the table name and optional DB schema for a model file.
     * Returns null if the file cannot be parsed or the class is not a valid Model.
     *
     * @return array{0: string, 1: ?string}|null  [$tableName, $schema]
     */
    public function getModelInfo(string $filePath): ?array
    {
        try {
            $parser = new ModelParser($filePath);
            $parsed = $parser->parse();
            return $this->resolveModelInfo($parsed->className);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Instantiate the model class (without calling its constructor) and extract
     * both the table name (source) and the optional DB schema (schema).
     *
     * @return array{0: string, 1: ?string}  [$tableName, $schema]
     */
    private function resolveModelInfo(string $className): array
    {
        $ref      = new \ReflectionClass($className);
        $instance = $ref->newInstanceWithoutConstructor();

        if (!$instance instanceof \Azera\Core\Model) {
            throw new RuntimeException(
                "Class {$className} is not an instance of Azera\\Mvc\\Model"
            );
        }

        return [$instance->source(), $instance->schema()];
    }

    private function buildProvider(Database $db): SchemaProvider
    {
        return SchemaProviderFactory::create($db);
    }
}


