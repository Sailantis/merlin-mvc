<?php

namespace Azera\Tests\Cli;

use Azera\Cli\Task;

/**
 * Task fixture combining task-wide, action-scoped middleware and interceptors.
 * Registered via Console::addTaskPath() in ConsolePipelineTest.
 */
class PipelineFixtureTask extends Task
{
    protected array $middlewares = [TaskMW::class];
    protected array $actionMiddlewares = ['runAction' => [ActionMW::class]];
    protected array $interceptors = [OuterITC::class, InnerITC::class];

    public function runAction(): void
    {
        ConsolePipelineTest::$trace[] = 'core';
    }

    /** Extra action to prove the removed hooks are not exposed as actions. */
    public function otherAction(): void
    {
        ConsolePipelineTest::$trace[] = 'other';
    }
}