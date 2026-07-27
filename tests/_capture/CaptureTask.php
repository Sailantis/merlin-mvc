<?php

namespace Azera\Cli\Tests;

use Azera\Cli\Task;

/**
 * Test fixture task: captures whatever options/params the dispatcher
 * passes to it. Used by console-process-argv-test.php to assert that
 * leading global flags reach the action method.
 */
class CaptureTask extends Task
{
    public static ?array $lastOptions = null;
    public static ?array $lastParams = null;
    public static bool $cleared = false;

    public function runAction(...$params): void
    {
        self::$lastOptions = $this->options;
        self::$lastParams = $params;
    }

    public function beforeAction(string $method, array $params): void
    {
        if (!self::$cleared) {
            self::$lastOptions = null;
            self::$lastParams = null;
            self::$cleared = true;
        }
    }

    public function afterAction(string $method, array $params): void
    {
        self::$cleared = false;
    }
}
