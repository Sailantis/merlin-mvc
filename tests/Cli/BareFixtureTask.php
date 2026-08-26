<?php

namespace Azera\Tests\Cli;

use Azera\Cli\Task;

/**
 * Task fixture with no middleware/interceptors — bare single action.
 */
class BareFixtureTask extends Task
{
    public function runAction(): void
    {
        ConsolePipelineTest::$trace[] = 'bare-core';
    }
}