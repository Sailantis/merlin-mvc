<?php

namespace Azera\Cli;

use Azera\Cli\Console\OutputRendering;
use Azera\Cli\Console\HelpRendering;
use Azera\Cli\Console\TaskDiscovery;
use ReflectionClass;

/**
 * Main Console class for registering and dispatching CLI tasks.
 *
 * Tasks are PHP classes that extend the base Task class and define public methods
 * ending with "Action". These methods can be invoked as CLI commands.
 *
 * The Console class supports automatic discovery of task classes in specified
 * namespaces and directories, as well as a built-in help system that extracts
 * descriptions from doc comments.
 */
class Console
{
    use OutputRendering;
    use TaskDiscovery;
    use HelpRendering;

    protected array $namespaces = ['App\\Tasks'];
    protected array $taskPaths = [];
    protected array $tasks = [
        'about'   => \Azera\Cli\Tasks\AboutTask::class,
        'cache'   => \Azera\Cli\Tasks\CacheTask::class,
        'db'      => \Azera\Cli\Tasks\DbTask::class,
        'make'    => \Azera\Cli\Tasks\MakeTask::class,
        'migrate' => \Azera\Cli\Tasks\MigrateTask::class,
        'model'   => \Azera\Cli\Tasks\ModelTask::class,
        'routes'  => \Azera\Cli\Tasks\RoutesTask::class,
        'serve'   => \Azera\Cli\Tasks\ServeTask::class,
        'test'    => \Azera\Cli\Tasks\TestTask::class,
    ]; // taskName => class

    protected string $scriptName;
    protected bool $coerceParams = false;
    /** @var string|null Raw help text (same format as docblock Options/Notes sections) shown globally in all help output. */
    protected ?string $globalHelp = null;
    /**
     * Explicit composer/project root used for PSR-4 resolution and task
     * discovery. When set, it takes precedence over the walk-up heuristic in
     * findComposerRoot(). This lets a bin script that already located the
     * project root point the Console at it directly.
     */
    protected ?string $composerRoot = null;

    //  Color / style constants, ANSI map, and output helpers
    //  now live in the ColorRendering trait.

    /**
     * "Shrink" marker: a line containing only this Unicode glyph (↰) in a
     * docblock tells the parser to render a tight line break — a new line
     * with NO blank line before it — instead of the default behaviour
     * (single \n collapses into the previous line; a blank line renders
     * a blank line). It is the compact counterpart of a blank line.
     *
     * The marker is stored internally as the ASCII record-separator (0x1E)
     * so it survives the section-splitting in parseDocComment() and can be
     * recognised by the renderers without colliding with real text.
     */
    public const SHRINK_MARKER = "\u{21B0}"; // ↰
    protected const SHRINK_SENTINEL = "\x1E";

    /**
     * Method names that end with 'Action' but are lifecycle hooks, not
     * dispatchable actions. They are excluded from help listings and from
     * the single-action task detection heuristic.
     */
    protected const RESERVED_ACTIONS = [
        'beforeAction' => true,
        'afterAction'  => true
    ];

    protected $sectionStyles = ['bmagenta', '#e998ee'];
    protected $taskStyles = ['bold', 'bgreen', '#21e194'];
    protected $actionStyles = ['bcyan', 'bold', '#2cc4eb'];
    protected $optionStyles = ['white', '#e7dbbd'];
    protected $braceStyles = ['bold', 'bgreen', '#23D18B'];
    protected $requiredArgStyles = ['bold', 'white'];
    protected $muteStyles = ['gray', '#a3a3a3'];
    protected $commentStyles = ['gray', '#bdbdbd'];

    /**
     * Console constructor.
     *
     * @param string|null $scriptName Optional custom script name for help output. Defaults to the basename of argv[0].
     */
    public function __construct(?string $scriptName = null)
    {
        $this->scriptName = $scriptName ?? basename($_SERVER['argv'][0] ?? 'console.php');
        $this->initColors();
    }

    /**
     * Set global help text that is appended to every help per-task detail
     * output. Use the same plain-text format as docblock Options sections:
     *
     *   --flag              One-line description
     *   --key=<value>       Description aligned automatically
     *
     * Pass null to clear previously set help.
     *
     * To suppress this section for a specific task, set
     * `protected bool $showGlobalHelp = false` on that task class.
     *
     * @param string|null $help The help text, or null to clear.
     */
    public function setGlobalTaskHelp(?string $help): void
    {
        $this->globalHelp = $help;
    }

    /**
     * Return the currently registered global task help text, or null if none is set.
     */
    public function getGlobalTaskHelp(): ?string
    {
        return $this->globalHelp;
    }

    // -------------------------------------------------------------------------
    //  Task registry / reflection helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the public dispatchable action methods on a task class,
     * excluding the reserved lifecycle hooks (beforeAction/afterAction).
     *
     * @return array<string,\ReflectionMethod>
     */
    protected function taskActions(string $class): array
    {
        $ref     = new ReflectionClass($class);
        $actions = [];
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            $name = $m->getName();
            if (!str_ends_with($name, 'Action')) {
                continue;
            }
            if (isset(static::RESERVED_ACTIONS[$name])) {
                continue;
            }
            $actions[$name] = $m;
        }
        return $actions;
    }

    /**
     * Return the single action method name for a task with exactly one
     * public dispatchable action, or null when the task has 0 or 2+ actions.
     *
     * @param string $class Fully-qualified task class name.
     * @return string|null The method name (e.g. "runAction") or null.
     */
    public function singleActionMethod(string $class): ?string
    {
        $actions = $this->taskActions($class);
        return count($actions) === 1 ? array_key_first($actions) : null;
    }

    /** Remove all registered tasks. Useful if you don't want to expose system tasks. */
    public function clearTasks(): void
    {
        $this->tasks = [];
    }

    // -------------------------------------------------------------------------
    //  Public API
    // -------------------------------------------------------------------------

    /**
     * Check whether automatic parameter type coercion is enabled.
     *
     * When enabled, string arguments that look like integers, floats, booleans,
     * or NULL are converted to the corresponding PHP scalar before being passed
     * to the action method.
     *
     * @return bool True if parameter coercion is enabled.
     */
    public function shouldCoerceParams(): bool
    {
        return $this->coerceParams;
    }

    /**
     * Enable or disable automatic parameter type coercion.
     *
     * @param bool $coerceParams True to enable coercion, false to pass all arguments as strings.
     */
    public function setCoerceParams(bool $coerceParams): void
    {
        $this->coerceParams = $coerceParams;
    }

    /**
     * Primary entry point. Accepts the full argv slice (without the script
     * name) and handles global flags before positional dispatch.
     *
     * Recognised forms:
     *   azera                              → overview help
     *   azera --help | -h                  → overview help
     *   azera help                         → overview help
     *   azera help <task>                  → help for <task>
     *   azera <task>                       → default action
     *   azera <task> --help | -h           → help for <task>
     *   azera <task> <action>              → dispatch action
     *   azera <task> <action> [args...]    → dispatch action with options
     *   azera [args...] <task> [args...]   → global opts are peeled off
     *                                              before positional parsing
     *
     * Tokens that look like flags (start with '-' or '--') and appear before
     * the first non-flag token are stripped from the positional stream and
     * stored in $globalOptions; tasks can read them via
     * $this->options('global.<name>') or via the merged $task->options array.
     *
     * @param string[] $argv Raw argv slice (argv[1..] from PHP's $argv).
     */
    public function process(array $argv): void
    {
        $this->autodiscover();

        // 1) Strip leading flag-only tokens. Stop as soon as we hit a
        //    non-flag token, which is always the task name (if any).
        $leadingFlags = [];
        $i            = 0;
        while ($i < count($argv)) {
            $tok = $argv[$i];
            if ($tok === '' || $tok[0] !== '-') {
                break;
            }
            // Reject the bare "-" stdin convention as a task name.
            if ($tok === '-') {
                break;
            }
            $leadingFlags[] = $tok;
            $i++;
        }
        $rest = array_slice($argv, $i);

        // 2) --help / -h anywhere in the remaining argv wins.
        //    The non-flag token immediately before --help (if any) names the
        //    help target. Otherwise show the overview.
        $helpIndex = null;
        foreach ($rest as $j => $t) {
            if ($t === '--help' || $t === '-h') {
                $helpIndex = $j;
                break;
            }
        }
        if ($helpIndex !== null) {
            $target = $rest[$helpIndex - 1] ?? null;
            if ($target !== null && $target !== '' && $target[0] !== '-') {
                // Strip the :verb suffix — help is per-task, not per-action
                $taskKey = strtolower(explode(':', $target, 2)[0]);
                $this->helpTask($taskKey);
            } else {
                $this->helpOverview();
            }
            return;
        }

        // 3) process "help" word
        $task = $rest[0] ?? null;
        if ($task === 'help') {
            $target = $rest[1] ?? null;
            if ($target) {
                // Strip the :verb suffix — help is per-task, not per-action
                $taskKey = strtolower(explode(':', $target, 2)[0]);
                $this->helpTask($taskKey);
            } else {
                $this->helpOverview();
            }
            return;
        }

        // 4) Empty after stripping flags + help = overview.
        if ($task === null || $task === '') {
            $this->helpOverview();
            return;
        }

        // 5) Parse the command token. It may be "domain:verb" (colon
        //    layout) or a bare "domain" (resolved to its single action or
        //    shown as task help in dispatch()).
        if (str_contains($task, ':')) {
            [$taskName, $action] = explode(':', $task, 2);
            $rest = array_slice($rest, 1);
        } elseif (!empty($rest[1]) && $rest[1][0] !== '-') {
            // Legacy two-word layout: "domain verb"
            $taskName = $task;
            $action   = $rest[1];
            $rest     = array_slice($rest, 2);
        } else {
            $taskName = $task;
            $action   = null;
            $rest     = array_slice($rest, 1);
        }

        if (!empty($leadingFlags)) {
            // Merge leading flags into the task's options array. This allows
            // tasks to read them via $this->options('global.<name>') or via
            // the merged $task->options array.
            $rest = array_merge($leadingFlags, $rest);
        }

        // 6) Normal positional dispatch. Options that belonged to the task
        //    (e.g. `azera model:sync-all --apply`) are still split by
        //    dispatch()'s call to splitArgs().
        $this->dispatch($taskName, $action, $rest);
    }

    protected function dispatch(string $taskName, ?string $actionName, array $params): void
    {
        // normalize task name
        $taskKey = strtolower($taskName);

        if (!isset($this->tasks[$taskKey])) {
            $this->stderr("Task '{$taskName}' not found. Run '{$this->scriptName} help' for available tasks.\n");
            return;
        }

        $class = $this->tasks[$taskKey];
        if (!class_exists($class)) {
            $this->stderr("Task class '{$class}' not loadable.\n");
            return;
        }

        $task = new $class();
        if (!$task instanceof Task) {
            $this->stderrln("Task class '{$class}' is not a valid Task.");
            return;
        }

        // Resolve the action method. A task with exactly one public
        // dispatchable action is a "single-action task": bare invocation
        // (azera <task>) runs that action, and any non-action token is
        // treated as its first positional parameter. For multi-action
        // tasks, bare invocation shows task help and an unrecognised
        // action name errors out — preventing typos from being swallowed.
        $singleMethod       = $this->singleActionMethod($class);
        $isSingleActionTask = $singleMethod !== null;
        $method             = $this->actionToMethod($actionName);

        if (!$method || !\method_exists($task, $method)) {
            if ($isSingleActionTask) {
                $method = $singleMethod;
                if ($actionName !== null && $actionName !== '') {
                    // treat the provided token as the first positional parameter
                    array_unshift($params, $actionName);
                }
            } else {
                if (!empty($actionName)) {
                    $message = "Action '" . ($actionName ?? '') . "' not found on task '{$taskName}'. Showing task help.";
                } else {
                    $message = "No action specified for task '{$taskName}'. Showing task help.";
                }
                $this->stderrln($this->style($message, ...self::STYLE_MUTED));
                $this->helpTask($taskKey);
                return;
            }
        }

        // call method with params
        [$params, $options] = $this->splitArgs($params);

        $task->options = $options;
        $task->console = $this;
        $task->beforeAction($method, $params);
        try {
            $task->$method(...$params);
        } finally {
            $task->afterAction($method, $params);
        }
    }

    /**
     * Convert an action token (dashed, colon, or snake) to the matching
     * camelCase `*Action` method name.
     */
    protected function actionToMethod(?string $action): ?string
    {
        if (!$action) {
            return null;
        }

        // convert dashed or colon or snake to camelCase then append Action
        $action = str_replace(':', '-', $action);
        $action = str_replace('_', '-', $action);
        $parts  = explode('-', $action);
        $camel  = array_shift($parts);
        foreach ($parts as $p) {
            $camel .= ucfirst($p);
        }
        return $camel . 'Action';
    }

    /**
     * Split a raw argv slice into positional parameters and named options.
     *
     * Recognised forms:
     *   --foo              → ['foo' => true]
     *   --foo=bar          → ['foo' => 'bar']
     *   --foo bar          → ['foo' => 'bar']  (next token consumed as value)
     *   --no-foo           → ['foo' => false]
     *   -f                 → ['f' => true]
     *   -f=bar             → ['f' => 'bar']
     *   -f bar             → ['f' => 'bar']
     *
     * Values are coerced via coerceParam() when coercion is enabled.
     *
     * @return array{0:array<int,string>,1:array<string,mixed>}
     */
    protected function splitArgs(array $args): array
    {
        $options = [];
        $params  = [];

        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];

            // long option: --foo or --foo=bar
            if (str_starts_with($arg, '--')) {
                $opt = substr($arg, 2);
                if (str_contains($opt, '=')) {
                    [$key, $value] = explode('=', $opt, 2);
                    $options[$key] = $this->coerceParam($value);
                } else {
                    // next argument is value or it's a flag
                    $next = $args[$i + 1] ?? null;
                    if ($next !== null && !str_starts_with($next, '-')) {
                        $options[$opt] = $this->coerceParam($next);
                        $i++;
                    } elseif (str_starts_with($opt, 'no-')) {
                        $options[substr($opt, 3)] = false;
                    } else {
                        $options[$opt] = true;
                    }
                }
                continue;
            }

            // short option: -f or -f=bar
            if (str_starts_with($arg, '-')) {
                $opt = substr($arg, 1);
                if (str_contains($opt, '=')) {
                    [$key, $value] = explode('=', $opt, 2);
                    $options[$key] = $this->coerceParam($value);
                } else {
                    $next = $args[$i + 1] ?? null;
                    if ($next !== null && !str_starts_with($next, '-')) {
                        $options[$opt] = $this->coerceParam($next);
                        $i++;
                    } else {
                        $options[$opt] = true;
                    }
                }
                continue;
            }

            // normal argument
            $params[] = $arg;
        }

        return [$params, $options];
    }

    /**
     * Coerce a string parameter to int, float, bool, or null if it looks like one of those.
     * Otherwise return the original string. Empty string is returned as-is.
     * @param string $param The parameter string to coerce.
     * @return int|float|bool|null|string The coerced value, or original string if no coercion applied.
     */
    public function coerceParam(string $param): int|float|bool|null|string
    {
        static $boolMap = [
            'true'  => true,
            'on'    => true,
            'false' => false,
            'off'   => false,
            'null'  => null,
        ];

        if (!$this->coerceParams) {
            return $param;
        }

        if ($param === '') {
            return '';
        }

        $lower = strtolower($param);

        // boolean/null
        if (isset($boolMap[$lower])) {
            return $boolMap[$lower];
        }

        if ($param[0] === '0') {
            // leading zero means string (to preserve things like "0123")
            return $param;
        }

        // integer
        if (preg_match('/^-?\d+$/', $param)) {
            return (int) $param;
        }

        // float
        if (preg_match('/^-?\d+\.\d+$/', $param)) {
            return (float) $param;
        }

        return $param;
    }
}