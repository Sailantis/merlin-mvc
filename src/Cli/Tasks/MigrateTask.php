<?php

namespace Azera\Cli\Tasks;

use Azera\AppContext;
use Azera\Cli\Task;
use Azera\Sync\ModelParser;
use Azera\Sync\Schema\SchemaProviderFactory;
use Azera\Sync\SchemaDiff;
use Azera\Sync\SqlGenerator;

/**
 * Generate SQL to bring the database schema in line with PHP model definitions.
 *
 * This is a one-directional tool: PHP → DB. It compares your model classes
 * against the live database and produces the ALTER/CREATE statements needed
 * to make the DB match the models.
 *
 * No migration tracking table or versioned files — the diff is always
 * computed fresh from the current state.
 *
 * Usage:
 *   migrate:diff                          Compare all models, output SQL
 *   migrate:diff --model=User             Compare a single model
 *   migrate:diff --apply                  Execute the SQL directly
 *   migrate:diff --file=output.sql        Write SQL to a file
 *   migrate:diff --dry-run                Show SQL without executing (default)
 *
 * Options:
 *   --model=<name>     Compare a single model (by class name or file path)
 *   --apply            Execute the generated SQL against the database
 *   --dry-run          Show SQL without executing (this is the default)
 *   --file=<path>      Write the generated SQL to a file
 *   --database=<role>  Database role to use (default: "read")
 *   --confirm          Skip confirmation prompt when using --apply
 *
 * Examples:
 *   migrate:diff
 *   migrate:diff --model=User
 *   migrate:diff --model=src/Models/User.php
 *   migrate:diff --apply
 *   migrate:diff --apply --confirm
 *   migrate:diff --file=database/migrations/add_avatar.sql
 */
class MigrateTask extends Task
{
    private SchemaDiff $schemaDiff;
    private SqlGenerator $sqlGenerator;

    public function __construct()
    {
        $this->schemaDiff = new SchemaDiff();
        $this->sqlGenerator = new SqlGenerator();
    }

    /**
     * Compare PHP model definitions against the database and generate SQL.
     */
    public function diffAction(): void
    {
        $projectRoot = $this->console->findComposerRoot();
        if ($projectRoot === null) {
            $this->error('No project root found (composer.json not detected).');
            return;
        }

        // Resolve the models to process
        $modelFiles = $this->resolveModelFiles($projectRoot);
        if (empty($modelFiles)) {
            $this->error('No model files found.');
            $this->muted('  Run "azera make:model" to create a model, or check your models directory.');
            return;
        }

        // Build database connection
        try {
            $db = $this->resolveDb();
        } catch (\Throwable $e) {
            $this->error("Database connection failed: {$e->getMessage()}");
            return;
        }

        $driver = $db->getDriver();
        $provider = SchemaProviderFactory::create($db);
        $allOps = [];
        $errors = [];

        foreach ($modelFiles as $file) {
            try {
                $parser = new ModelParser($file);
                $parsed = $parser->parse();
                $info = $this->resolveModelInfo($parsed->className);
                $table = $info[0];
                $schema = $info[1];

                // Check if the table exists
                try {
                    $tableSchema = $provider->getTableSchema($table, $schema);
                } catch (\Throwable) {
                    $tableSchema = null;
                }

                $ops = $this->schemaDiff->diffTableExists($parsed, $tableSchema);

                if (!empty($ops)) {
                    $shortClass = basename(str_replace('\\', '/', $parsed->className));
                    $this->info("Changes for {$shortClass} ({$table}):");
                    foreach ($ops as $op) {
                        $this->line('  ' . $this->style('•', 'bmagenta') . ' ' . $this->describeOp($op));
                    }
                    $this->line('');
                    $allOps = array_merge($allOps, $ops);
                }
            } catch (\Throwable $e) {
                $errors[] = basename($file) . ': ' . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            $this->warn('Errors:');
            foreach ($errors as $err) {
                $this->line('  ' . $this->style('•', 'byellow') . ' ' . $err);
            }
            $this->line('');
        }

        if (empty($allOps)) {
            $this->success('All models are in sync with the database. No changes needed.');
            return;
        }

        // Generate SQL
        $sqlStatements = $this->sqlGenerator->generate($allOps, $driver);

        if (empty($sqlStatements)) {
            $this->success('All models are in sync with the database. No changes needed.');
            return;
        }

        // Output SQL
        $this->line($this->style('Generated SQL:', 'bold'));
        $this->line('');
        foreach ($sqlStatements as $sql) {
            $this->line('  ' . $this->style($sql, 'cyan'));
        }
        $this->line('');

        // Write to file if requested
        $file = $this->option('file');
        if ($file !== null) {
            $this->writeToFile($file, $sqlStatements);
            return;
        }

        // Execute if requested
        if ($this->option('apply', false)) {
            $this->executeSql($db, $sqlStatements, count($allOps));
            return;
        }

        $this->muted('Review the SQL above, then run with --apply to execute, or --file=<path> to save.');
    }

    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    private function resolveDb(): \Azera\Db\Database
    {
        $role = (string) $this->option('database', 'default');
        $ctx = AppContext::instance();
        return $ctx->dbManager()->getOrDefault($role);
    }

    /**
     * Resolve model files based on the --model option or auto-discovery.
     *
     * @return string[]  Absolute file paths
     */
    private function resolveModelFiles(string $projectRoot): array
    {
        $singleModel = $this->option('model');
        if ($singleModel !== null) {
            return [$this->resolveSingleModel($singleModel)];
        }

        // Auto-discover models directory
        $modelsDir = $this->resolveModelsDir();
        if ($modelsDir === null) {
            return [];
        }

        return $this->console->scanDirectory($modelsDir);
    }

    /**
     * Resolve a single model by name or path.
     */
    private function resolveSingleModel(string $model): string
    {
        // If it's a file path, use directly
        if (str_contains($model, '/') || str_contains($model, '\\') || str_ends_with($model, '.php')) {
            $abs = is_file($model) ? realpath($model) : ($this->console->findComposerRoot() . '/' . $model);
            if (is_file($abs)) {
                return $abs;
            }
            $this->error("Model file not found: {$model}");
            exit(1);
        }

        // Try PSR-4 resolution: "User" → "App\Models\User"
        $candidates = [
            'App\\Models\\' . $model,
            'App\\' . $model,
        ];

        foreach ($candidates as $fqn) {
            $path = $this->console->resolvePsr4Path($fqn);
            if ($path !== null) {
                $file = $path . '/' . basename(str_replace('\\', '/', $fqn)) . '.php';
                if (is_file($file)) {
                    return $file;
                }
            }
        }

        // Try scanning for the file
        $modelsDir = $this->resolveModelsDir();
        if ($modelsDir !== null) {
            foreach ($this->console->scanDirectory($modelsDir) as $file) {
                if (basename($file, '.php') === $model) {
                    return $file;
                }
            }
        }

        $this->error("Model not found: {$model}");
        exit(1);
    }

    /**
     * Resolve the models directory using PSR-4 or convention.
     */
    private function resolveModelsDir(): ?string
    {
        $path = $this->console->resolvePsr4Path('App\\Models');
        if ($path !== null) {
            return $path;
        }

        $projectRoot = $this->console->findComposerRoot();
        if ($projectRoot !== null) {
            foreach (['app/Models', 'Models', 'src/Models', 'App/Models'] as $rel) {
                $abs = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                if (is_dir($abs)) {
                    return realpath($abs);
                }
            }
        }

        return null;
    }

    /**
     * Resolve table name and optional schema from a model class.
     *
     * @return array{0: string, 1: ?string}
     */
    private function resolveModelInfo(string $className): array
    {
        $ref = new \ReflectionClass($className);
        $instance = $ref->newInstanceWithoutConstructor();

        if (!$instance instanceof \Azera\Orm\Model) {
            throw new \RuntimeException(
                "Class {$className} is not an instance of Azera\\Orm\\Model"
            );
        }

        return [$instance->source(), $instance->schema()];
    }

    private function writeToFile(string $path, array $sqlStatements): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = implode("\n\n", $sqlStatements) . "\n";
        file_put_contents($path, $content);

        $this->success("SQL written to {$path}");
    }

    private function executeSql(\Azera\Db\Database $db, array $sqlStatements, int $changeCount): void
    {
        if (!$this->option('confirm', false)) {
            $this->warn("This will execute {$changeCount} change(s) against the database.");
            $this->muted('  Add --confirm to skip this prompt.');
            $this->write('  Continue? [y/N] ');
            $handle = fopen('php://stdin', 'r');
            $answer = strtolower(trim(fgets($handle)));
            fclose($handle);

            if ($answer !== 'y' && $answer !== 'yes') {
                $this->muted('Aborted.');
                return;
            }
        }

        $pdo = $db->getInternalConnection();
        $applied = 0;
        $errors = 0;

        try {
            $pdo->beginTransaction();

            foreach ($sqlStatements as $sql) {
                // Skip comment-only lines
                $trimmed = ltrim($sql);
                if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                    continue;
                }

                try {
                    $pdo->exec($sql);
                    $applied++;
                } catch (\Throwable $e) {
                    $this->error("Failed: {$sql}");
                    $this->muted("  Error: {$e->getMessage()}");
                    $errors++;
                }
            }

            if ($errors === 0) {
                $pdo->commit();
                $this->success("Applied {$applied} SQL statement(s) successfully.");
            } else {
                $pdo->rollBack();
                $this->error("Rolled back due to {$errors} error(s). {$applied} statement(s) succeeded before the error.");
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->error("Transaction failed: {$e->getMessage()}");
        }
    }

    private function describeOp($op): string
    {
        return match (true) {
            $op instanceof \Azera\Sync\CreateTable => "CREATE TABLE `{$op->table}` (" . count($op->columns) . " columns)",
            $op instanceof \Azera\Sync\AddColumn => "ADD COLUMN `{$op->column}` {$op->type} on `{$op->table}`",
            $op instanceof \Azera\Sync\DropColumn => "DROP COLUMN `{$op->column}` from `{$op->table}`",
            $op instanceof \Azera\Sync\AlterColumn => "ALTER COLUMN `{$op->column}` on `{$op->table}` ({$op->oldType} → {$op->newType})",
            $op instanceof \Azera\Sync\DropIndex => "DROP INDEX `{$op->index}`",
            default => get_class($op)
        };
    }
}