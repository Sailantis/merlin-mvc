#!/usr/bin/env php
<?php
/**
 * ModelSyncExample CLI entry point.
 *
 * Usage:
 *   php console.php <task> <action> [args...]
 *
 * Sync examples:
 *   php console.php model-sync all  Models                                          # dry-run
 *   php console.php model-sync all  Models --apply                                  # apply
 *   php console.php model-sync all  Models --apply --generate-accessors             # with getters/setters
 *   php console.php model-sync all  Models --apply --field-visibility=protected     # protected fields
 *   php console.php model-sync all  Models --apply --no-deprecate                   # skip @deprecated tags
 *   php console.php model-sync all  Models --apply --create-missing \
 *       --namespace=ModelSyncExample\\Models                                        # scaffold new models
 *   php console.php model-sync model Models/User.php --apply
 *   php console.php model-sync model Models/User.php --apply --generate-accessors
 *   php console.php model-sync make Order Models --namespace=ModelSyncExample\\Models --apply
 */

chdir(__DIR__);

require __DIR__ . '/bootstrap.php';

use Azera\Cli\Console;

$console = new Console();

$console->process(
    array_slice($argv, 1)
);
