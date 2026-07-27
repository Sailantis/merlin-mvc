<?php
/**
 * Smoke test for the new process() entry point.
 *
 * Verifies:
 *  - process() with a leading flag no longer errors out as "task not found"
 *  - leading global flags are merged into the action's $options bag
 *  - --help and -h are intercepted at any position
 *  - "help" word still works for back-compat
 *  - process() back-compat shim still works the old way
 *
 * Run from the framework root:
 *   php tests/console-process-argv-test.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/_capture/CaptureTask.php';

use Azera\Cli\Console;
use Azera\Cli\Tests\CaptureTask;

$failures = 0;
$passes = 0;

function assertTrue(bool $cond, string $label): void
{
    global $failures, $passes;
    if ($cond) {
        $passes++;
        echo "  [ok]  $label\n";
    } else {
        $failures++;
        echo "  [FAIL] $label\n";
    }
}

function freshConsole(): Console
{
    $c = new Console('azera');
    $c->addTaskPath(__DIR__ . '/_capture', false);
    return $c;
}

// ── Test 1: leading --key=val flag no longer collides with task slot ─────
echo "\n[1] leading --key=val flag is no longer treated as a task name\n";
CaptureTask::$lastOptions = null;

$console = freshConsole();
$console->process(['--provider=Azera\\AppContext', 'capture']);

assertTrue(CaptureTask::$lastOptions !== null, 'action was invoked (no "task not found" error)');
assertTrue(
    (CaptureTask::$lastOptions['provider'] ?? null) === 'Azera\\AppContext',
    'leading --provider=val was merged into action options'
);

// ── Test 2: --help is intercepted anywhere in argv ───────────────────────
echo "\n[2] --help / -h intercepted\n";

ob_start();
freshConsole()->process(['--help']);
$out = ob_get_clean();
assertTrue(
    stripos($out, 'Usage') !== false || stripos($out, 'available') !== false,
    'azera --help shows overview'
);

ob_start();
freshConsole()->process(['-h']);
$out = ob_get_clean();
assertTrue(
    stripos($out, 'Usage') !== false || stripos($out, 'available') !== false,
    'azera -h shows overview'
);

ob_start();
freshConsole()->process(['help']);
$out = ob_get_clean();
assertTrue(
    stripos($out, 'Usage') !== false || stripos($out, 'available') !== false,
    'azera help still works'
);

// ── Test 3: action-level flags after task still work (back-compat) ──────
echo "\n[3] task-level flags after the task name still reach the action\n";
CaptureTask::$lastOptions = null;
freshConsole()->process(['capture', '--apply', '--namespace=Foo']);
assertTrue(
    (CaptureTask::$lastOptions['apply'] ?? null) === true,
    'task-level --apply flag preserved'
);
assertTrue(
    (CaptureTask::$lastOptions['namespace'] ?? null) === 'Foo',
    'task-level --namespace=val flag preserved'
);

// ── Test 4: mix of leading globals and task-level flags ─────────────────
echo "\n[4] leading globals and task-level flags coexist\n";
CaptureTask::$lastOptions = null;
freshConsole()->process(['--provider=Bar', 'capture', '--apply']);
assertTrue(
    (CaptureTask::$lastOptions['provider'] ?? null) === 'Bar',
    'leading --provider survives alongside task-level options'
);
assertTrue(
    (CaptureTask::$lastOptions['apply'] ?? null) === true,
    'task-level --apply still present'
);

// ── Test 6: bare '-' is treated as positional, not a flag ────────────────
// The '-' token is the conventional stdin marker. It must NOT be silently
// consumed as a global flag, so the dispatcher should report it as a
// missing task — which is the same outcome as any other unknown task name.
// We shell out so the test harness can read both stdout and stderr cleanly.
echo "\n[6] bare '-' is not silently consumed as a global flag\n";

$autoload = realpath(__DIR__ . '/../vendor/autoload.php');
$taskPath = realpath(__DIR__ . '/_capture');

$probeScript = '<?php'
    . "\nrequire " . var_export($autoload, true) . ";"
    . "\n\$c = new Azera\\Cli\\Console('azera');"
    . "\n\$c->addTaskPath(" . var_export($taskPath, true) . ", false);"
    . "\n\$c->autodiscover();"
    . "\n\$c->process(['-']);";

$probeFile = tempnam(sys_get_temp_dir(), 'capture_probe_');
file_put_contents($probeFile, $probeScript);

$cmd = escapeshellarg(PHP_BINARY)
     . ' -d display_errors=0 ' . escapeshellarg($probeFile)
     . ' 2>&1';

$combined = shell_exec($cmd);
unlink($probeFile);

assertTrue(
    is_string($combined) && stripos($combined, "Task '-' not found") !== false,
    "'-' is reported as a missing task (stderr message) rather than being swallowed as a flag"
);

// ── Summary ─────────────────────────────────────────────────────────────
echo "\n────────────────────────\n";
echo "Passed: $passes\n";
echo "Failed: $failures\n";
exit($failures === 0 ? 0 : 1);
