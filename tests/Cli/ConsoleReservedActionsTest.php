<?php
namespace Azera\Tests\Cli;

require_once __DIR__ . '/../../vendor/autoload.php';

use Azera\Cli\Console;
use Azera\Cli\Task;
use PHPUnit\Framework\TestCase;

// ---------------------------------------------------------------------------
// Stub task with real actions and the inherited lifecycle hooks
// ---------------------------------------------------------------------------

class MultiActionTask extends Task
{
    /** Does something useful. */
    public function runAction(): void
    {
    }

    /** Does something else. */
    public function importAction(): void
    {
    }
}

class SingleActionTask extends Task
{
    /** Only action. */
    public function runAction(): void
    {
    }
}

// ---------------------------------------------------------------------------
// Expose the protected helper so we can assert on it
// ---------------------------------------------------------------------------

class TestableConsole extends Console
{
    public function publicExtractActionDescriptions(string $class): array
    {
        return $this->extractActionDescriptions($class);
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class ConsoleReservedActionsTest extends TestCase
{
    private TestableConsole $console;

    protected function setUp(): void
    {
        $this->console = new TestableConsole();
    }

    public function testBeforeActionIsNotListedAsAction(): void
    {
        $actions = $this->console->publicExtractActionDescriptions(MultiActionTask::class);

        $this->assertArrayNotHasKey(
            'before',
            $actions,
            '"before" (beforeAction) must not appear in the extracted action list'
        );
    }

    public function testAfterActionIsNotListedAsAction(): void
    {
        $actions = $this->console->publicExtractActionDescriptions(MultiActionTask::class);

        $this->assertArrayNotHasKey(
            'after',
            $actions,
            '"after" (afterAction) must not appear in the extracted action list'
        );
    }

    public function testRealActionsAreStillListed(): void
    {
        $actions = $this->console->publicExtractActionDescriptions(MultiActionTask::class);

        $this->assertArrayHasKey('run', $actions);
        $this->assertArrayHasKey('import', $actions);
        $this->assertCount(2, $actions, 'Only the two real actions should be listed');
    }

    public function testSingleActionTaskIsNotInflatedByHooks(): void
    {
        // A task with exactly one real action (runAction) plus the two inherited
        // hooks.  Without filtering, publicActionCount would be 3 and the task
        // would be classified as multi-action.  With filtering it must be 1.
        $actions = $this->console->publicExtractActionDescriptions(SingleActionTask::class);

        $this->assertCount(1, $actions, 'Single-action task should report exactly 1 action');
        $this->assertArrayHasKey('run', $actions);
    }
}
