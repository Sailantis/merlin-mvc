<?php

namespace Azera\Cli\Tasks;

use Azera\Cli\Task;

/**
 * Run the project's test suite.
 *
 * Automatically detects the installed test runner (PHPUnit, Pest, or
 * Codeception) and delegates to it. All unknown options are passed
 * through to the underlying runner.
 *
 * Usage:
 *   test                       Run all tests
 *   test --filter=UserTest     Run tests matching a filter
 *   test --group=api           Run tests in a group
 *   test --bootstrap           Boot AppContext before running
 *
 * Options:
 *   --bootstrap        Boot the AppContext before running tests (useful
 *                      for integration tests that need DB connections)
 *   --runner=<path>    Path to a custom test runner binary
 *   --colors           Force colored output (passed through to PHPUnit)
 *
 * Any other flags (e.g. --filter, --group, --verbose) are forwarded
 * directly to the test runner.
 *
 * Exit code matches the test runner's exit code.
 *
 * Examples:
 *   test
 *   test --filter=UserControllerTest
 *   test --group=api
 *   test --runner=vendor/bin/pest
 *   test --bootstrap --filter=DatabaseTest
 */
class TestTask extends Task
{
    /** Known test runner binaries, in order of preference. */
    private const RUNNERS = [
        'vendor/bin/phpunit',
        'vendor/bin/pest',
        'vendor/bin/codecept',
        'vendor/bin/phpunit.bat',
        'vendor/bin/pest.bat',
        'vendor/bin/codecept.bat',
    ];

    public function runAction(): void
    {
        $projectRoot = $this->console->findComposerRoot();
        if ($projectRoot === null) {
            $this->error('No project root found (composer.json not detected).');
            return;
        }

        // Optional: boot AppContext for integration tests
        if ($this->option('bootstrap', false)) {
            $this->info('Booting AppContext...');
            try {
                \Azera\AppContext::instance();
                $this->success('AppContext booted.');
            } catch (\Throwable $e) {
                $this->error("Failed to boot AppContext: {$e->getMessage()}");
                return;
            }
        }

        // Resolve the test runner binary
        $runner = $this->resolveRunner($projectRoot);
        if ($runner === null) {
            $this->error('No test runner found.');
            $this->muted('Install PHPUnit:  composer require --dev phpunit/phpunit');
            $this->muted('Or Pest:         composer require --dev pestphp/pest');
            return;
        }

        $runnerLabel = basename($runner);
        $this->muted("Using: {$runnerLabel}");

        // Build command — forward all unknown options
        $args = $this->buildArgs();
        $cmd  = escapeshellarg($runner);
        if (!empty($args)) {
            $cmd .= ' ' . implode(' ', array_map('escapeshellarg', $args));
        }

        $this->line('');
        passthru($cmd, $exitCode);
    }

    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    /**
     * Find the test runner binary. Checks --runner option first, then
     * known paths relative to the project root.
     */
    private function resolveRunner(string $projectRoot): ?string
    {
        $custom = $this->option('runner');
        if ($custom !== null) {
            $path = $projectRoot . '/' . ltrim($custom, '/');
            return is_file($path) ? $path : null;
        }

        foreach (self::RUNNERS as $rel) {
            $path = $projectRoot . '/' . $rel;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Build the argument list to forward to the test runner.
     * Extracts known task-level options and passes everything else through.
     */
    private function buildArgs(): array
    {
        $known = ['bootstrap', 'runner', 'colors'];
        $args  = [];

        // Always pass --colors=always for consistent output
        $args[] = '--colors=always';

        foreach ($this->options as $key => $value) {
            if (in_array($key, $known, true)) {
                continue;
            }
            if ($value === true) {
                $args[] = "--{$key}";
            } elseif ($value !== false && $value !== null) {
                $args[] = "--{$key}=" . $value;
            }
        }

        return $args;
    }
}