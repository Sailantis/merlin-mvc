<?php

namespace Merlin\Cli;

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
    protected array $namespaces = ['App\\Tasks'];
    protected array $taskPaths = [];
    protected array $tasks = [
        'model-sync' => \Merlin\Cli\Tasks\ModelSyncTask::class,
    ]; // taskName => class
    /** @var array<string,string> class => absolute file path, populated during cold discovery */
    protected array $taskClassFiles = [];
    protected string $scriptName;
    protected bool $coerceParams = false;
    protected bool $useColors;
    protected string $defaultAction = "runAction";
    /** @var string|null Raw help text (same format as docblock Options/Notes sections) shown globally in all help output. */
    protected ?string $globalHelp = null;

    protected const ANSI = [
        'reset' => "\033[0m",
        // basic styles
        'bold' => "\033[1m",
        'dim' => "\033[2m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        // Bright variants (prefix with 'b')
        'gray' => "\033[90m",
        'bred' => "\033[91m",
        'bgreen' => "\033[92m",
        'byellow' => "\033[93m",
        'bblue' => "\033[94m",
        'bmagenta' => "\033[95m",
        'bcyan' => "\033[96m",
        // Background colors (prefix with 'bg-')
        'bg-black' => "\033[40m",
        'bg-red' => "\033[41m",
        'bg-green' => "\033[42m",
        'bg-yellow' => "\033[43m",
        'bg-blue' => "\033[44m",
        'bg-magenta' => "\033[45m",
        'bg-cyan' => "\033[46m",
        'bg-white' => "\033[47m",
    ];


    public const STYLE_ERROR = ['bg-red', 'white', 'bold'];
    public const STYLE_WARN = ['byellow'];
    public const STYLE_INFO = ['bcyan'];
    public const STYLE_SUCCESS = ['bgreen'];
    public const STYLE_MUTED = ['gray'];

    /**
     * Method names that end with 'Action' but are lifecycle hooks, not
     * dispatchable actions. They are excluded from help listings and from
     * the single-action task detection heuristic.
     */
    protected const RESERVED_ACTIONS = [
        'beforeAction' => true,
        'afterAction' => true
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
    public function __construct(string $scriptName = null)
    {
        $this->scriptName = $scriptName ?? basename($_SERVER['argv'][0] ?? 'console.php');
        $this->useColors = $this->detectColorSupport();
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

    /**
     * Register a namespace to search for tasks. Namespaces are resolved to directories via PSR-4 rules.
     * By default, "App\\Tasks" is registered. The framework's own built-in tasks are pre-registered
     * directly without any filesystem scan.
     */
    public function addNamespace(string $ns): void
    {
        $ns = trim($ns, '\\');
        if (!in_array($ns, $this->namespaces, true)) {
            $this->namespaces[] = $ns;
        }
    }

    /**
     * Register a directory path to search for task classes. This is in addition to any namespaces registered via addNamespace().
     * You can set $registerAutoload to true to automatically register a simple PSR-4 autoloader for this path.
     */
    public function addTaskPath(string $path, bool $registerAutoload = false): void
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        if (!in_array($path, $this->taskPaths, true)) {
            $this->taskPaths[] = $path;
            if ($registerAutoload) {
                $this->registerSimpleAutoload($path);
            }
        }
    }

    /**
     * Get the default action method name used when no action is specified on the command line.
     *
     * @return string Default action method name (without namespace), e.g. "runAction".
     */
    public function getDefaultAction(): string
    {
        return $this->defaultAction;
    }

    /**
     * Set the default action method name used when no action is specified on the command line.
     *
     * @param string $defaultAction Action method name, e.g. "runAction".
     * @throws \InvalidArgumentException If the given name is empty.
     */
    public function setDefaultAction(string $defaultAction): void
    {
        if (empty($defaultAction)) {
            throw new \InvalidArgumentException("Default action cannot be empty");
        }
        $this->defaultAction = $defaultAction;
    }


    /** Remove all registered tasks. Useful if you don't want to expose system tasks. */
    public function clearTasks(): void
    {
        $this->tasks = [];
    }

    // -------------------------------------------------------------------------
    //  Color / output helpers
    // -------------------------------------------------------------------------

    protected function detectColorSupport(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return function_exists('sapi_windows_vt100_support')
                && @sapi_windows_vt100_support(STDOUT);
        }
        return function_exists('stream_isatty') && stream_isatty(STDOUT);
    }

    /**
     * Enable or disable ANSI color output explicitly.
     */
    public function enableColors(bool $colors): void
    {
        $this->useColors = $colors;
    }

    /** Check whether ANSI color output is enabled. */
    public function hasColors(): bool
    {
        return $this->useColors;
    }

    /**
     * Generate an ANSI escape code for a custom RGB color.
     *
     * @param string|int $r Either a hex color code (e.g. "#ff0000" or "bg:#00ff00" or "bg #00ff00") or the red component (0-255).
     * @param int|null $g The green component (0-255), required if $r is not a hex code.
     * @param int|null $b The blue component (0-255), required if $r is not a hex code.
     * @param bool $background Whether this color is for background (true) or foreground (false).
     * @return string The ANSI escape code for the specified color, or an empty string if colors are disabled or input is invalid.
     */
    public function color(string|int $r, ?int $g = null, ?int $b = null, $background = false): string
    {
        if (!$this->useColors) {
            return '';
        }

        $code = $background ? 48 : 38;

        // Hex-Mode?
        if ($g === null && $b === null) {
            $hex = (string) $r;

            if (str_starts_with($hex, 'bg')) {
                // Set Background explicitly
                $code = 48;
                $hex = ltrim(substr($hex, 2), ' :;-');
            } elseif (str_starts_with($hex, 'fg')) {
                // Set Foreground explicitly
                $code = 38;
                $hex = ltrim(substr($hex, 2), ' :;-');
            } elseif (str_starts_with($hex, "\033")) {
                // Already an ANSI code
                return $hex;
            }

            // Remove '#' character
            $hex = ltrim($hex, '#');

            // Short form #abc → #aabbcc
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }

            if (strlen($hex) !== 6) {
                return '';
            }

            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }

        return "\033[{$code};2;{$r};{$g};{$b}m";
    }


    /**
     * Apply one or more named ANSI styles or a custom color to a string.
     * Style names: bold, dim, red, green, yellow, blue, magenta, cyan, white, gray, bred, bgreen, byellow, bcyan, bg-red, bg-green, bg-yellow, bg-blue, bg-magenta, bg-cyan, bg-white
     * Custom colors can be specified via hex code (e.g. "#ff0000" or "bg:#00ff00" or "bg #00ff00").
     *
     * When color support is disabled, the text is returned unchanged.
     */
    public function style(string $text, string ...$styles): string
    {
        if (!$this->useColors || empty($styles)) {
            return $text;
        }
        $open = '';
        foreach ($styles as $s) {
            $open .= self::ANSI[$s] ?? $this->color($s) ?: $s;
        }
        return $open . $text . self::ANSI['reset'];
    }

    /** Write text to stdout. */
    public function write(string $text = ''): void
    {
        echo $text;
    }

    /** Write a line to stdout (newline appended). */
    public function writeln(string $text = ''): void
    {
        echo $text . PHP_EOL;
    }

    /** Write text to stderr. */
    public function stderr(string $text): void
    {
        fwrite(STDERR, $text);
    }

    /** Write a line to stderr (newline appended). */
    public function stderrln(string $text): void
    {
        fwrite(STDERR, $text . PHP_EOL);
    }

    /** Plain informational line. */
    public function line(string $text): void
    {
        $this->writeln($text);
    }

    /**
     * Write an informational message (cyan). Newline is appended automatically.
     */
    public function info(string $text): void
    {
        $this->writeln($this->style($text, ...static::STYLE_INFO));
    }

    /**
     * Write a success message (green). Newline is appended automatically.
     */
    public function success(string $text): void
    {
        $this->writeln($this->style($text, ...static::STYLE_SUCCESS));
    }

    /** 
     * Write a warning message (yellow). Newline is appended automatically.
     */
    public function warn(string $text): void
    {
        $this->writeln($this->style($text, ...static::STYLE_WARN));
    }

    /** 
     * Write an error message (white on red) to STDERR. Newline is appended automatically.
     */
    public function error(string $text): void
    {
        $this->stderrln($this->style($text, ...static::STYLE_ERROR));
    }

    /** 
     * Write a muted / dimmed message. Newline is appended automatically.
     */
    public function muted(string $text): void
    {
        $this->writeln($this->style($text, ...static::STYLE_MUTED));
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
     * Process the given task, action, and parameters.
     *
     * @param string|null $task The name of the task to execute.
     * @param string|null $action The name of the action to execute within the task.
     * @param array $params An array of parameters to pass to the action method.
     */
    public function process(?string $task = null, ?string $action = null, array $params = []): void
    {
        $this->autodiscover();

        // If no task provided, show overview
        if (!$task) {
            $this->helpOverview();
            return;
        }

        // help handling
        if ($task === 'help') {
            $target = $action ?? null;
            if ($target) {
                $this->helpTask($target);
            } else {
                $this->helpOverview();
            }
            return;
        }

        // run the requested task/action
        $this->dispatch($task, $action, $params);
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

        // determine method name
        $method = $this->actionToMethod($actionName);

        if (!$method || !\method_exists($task, $method)) {
            // Only fall back to the default action automatically when no 
            // action was specified, or when this is a single-action task
            // (so the "action" arg is actually the first positional param). 
            // For multi-action tasks with an unrecognised action name, show
            // task help instead to prevent silently swallowing typos.
            $hasDefault = \method_exists($task, $this->defaultAction);
            $ref = new ReflectionClass($class);
            $publicActionCount = 0;
            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
                $name = $m->getName();
                if (!str_ends_with($name, 'Action')) {
                    continue;
                }
                if (isset(static::RESERVED_ACTIONS[$name])) {
                    continue;
                }
                $publicActionCount++;
            }
            $isSingleActionTask = $publicActionCount <= 1;

            if ($hasDefault && ($actionName === null || $actionName === '' || $isSingleActionTask)) {
                $method = $this->defaultAction;
                if ($actionName !== null && $actionName !== '') {
                    // treat the provided action as the first positional parameter
                    array_unshift($params, $actionName);
                }
            } else {
                if (!empty($actionName)) {
                    $message = "Action '" . ($actionName ?? '') . "' not found on task '{$taskName}'. Showing task help.";
                } else {
                    $message = "No action specified for task '{$taskName}' and no default action found. Showing task help.";
                }
                $this->stderrln($this->style($message, ...static::STYLE_MUTED));
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

    protected function actionToMethod(?string $action): ?string
    {
        if (!$action) {
            return null;
        }

        // convert dashed or colon or snake to camelCase then append Action
        $action = str_replace(':', '-', $action);
        $action = str_replace('_', '-', $action);
        $parts = explode('-', $action);
        $camel = array_shift($parts);
        foreach ($parts as $p) {
            $camel .= ucfirst($p);
        }
        return $camel . 'Action';
    }

    /** Autodiscover tasks in all registered namespaces and paths */
    public function autodiscover(): void
    {
        foreach ($this->namespaces as $ns) {
            $this->discoverNamespaceViaComposer($ns);
        }

        $this->discoverComposerNamespaces();

        foreach ($this->taskPaths as $path) {
            $this->discoverPath($path);
        }
    }

    protected function discoverNamespaceViaComposer(string $ns): void
    {
        $path = $this->resolvePsr4Path($ns);
        if ($path !== null) {
            $this->discoverPath($path);
        }
    }

    protected function discoverComposerNamespaces(): void
    {
        foreach ($this->readComposerPsr4() as $dir) {
            // Recursively find every *Task.php under this PSR-4 root
            foreach ($this->scanDirectory($dir, 'Task.php') as $file) {
                $this->registerTaskFile($file);
            }
        }
    }

    /**
     * Return the full PSR-4 map from the nearest composer.json.
     * Result is cached for the lifetime of this Console instance.
     *
     * @return array<string,string> namespace prefix => absolute directory
     */
    public function readComposerPsr4(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $composerDir = $this->findComposerRoot();
        if ($composerDir === null) {
            return $cache = [];
        }
        $json = json_decode(file_get_contents($composerDir . '/composer.json'), true);
        $raw = $json['autoload']['psr-4'] ?? [];
        $result = [];
        foreach ($raw as $ns => $dir) {
            $result[$ns] = rtrim($composerDir . DIRECTORY_SEPARATOR . ltrim($dir, '/\\'), DIRECTORY_SEPARATOR);
        }
        return $cache = $result;
    }

    protected function getMainScriptDirectory(): string
    {
        static $dir = null;
        if ($dir === null) {
            $dir = dirname(get_included_files()[0]);
        }
        return $dir;
    }

    /**
     * Walk up the directory tree from this file until composer.json is found.
     * Falls back to the current working directory.
     */
    public function findComposerRoot(): ?string
    {
        static $cache = false;
        if ($cache !== false) {
            return $cache;
        }

        // Walk up from the currently executing script, which is the most likely location for composer.json in a typical project.
        $dir = $this->getMainScriptDirectory();

        while (true) {
            if (is_file($dir . '/composer.json')) {
                return $cache = $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return $cache = null;
    }

    /**
     * Resolve a PHP namespace to an absolute directory using the PSR-4 map.
     * Falls back to guessing a path relative to the current working directory.
     *
     * Example: "App\\Models" => "/project/src/Models"
     */
    public function resolvePsr4Path(string $namespace): ?string
    {
        $map = $this->readComposerPsr4();
        $nsClean = rtrim($namespace, '\\');
        $bestPrefix = null;
        $bestDir = null;
        foreach ($map as $prefix => $dir) {
            $prefixClean = rtrim($prefix, '\\');
            if ($nsClean === $prefixClean || str_starts_with($nsClean . '\\', $prefixClean . '\\')) {
                if ($bestPrefix === null || strlen($prefixClean) > strlen($bestPrefix)) {
                    $bestPrefix = $prefixClean;
                    $bestDir = $dir;
                }
            }
        }
        if ($bestPrefix !== null) {
            $suffix = ltrim(substr($nsClean, strlen($bestPrefix)), '\\');
            $path = $suffix
                ? $bestDir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $suffix)
                : $bestDir;
            return is_dir($path) ? $path : null;
        }
        // Fallback: guess a path relative to the current script directory
        $guess = $this->getMainScriptDirectory() . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $nsClean);
        return is_dir($guess) ? $guess : null;
    }

    /**
     * Recursively scan $dir and return sorted absolute paths to files whose
     * name ends with $suffix (default ".php").
     *
     * @return string[]
     */
    public function scanDirectory(string $dir, string $suffix = '.php'): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        $files = [];
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                $files[] = $file->getRealPath();
            }
        }
        sort($files);
        return $files;
    }

    /**
     * Extract the fully-qualified class name from a PHP source file by
     * parsing its namespace declaration and the file's base name.
     */
    public function extractClassFromFile(string $file): ?string
    {
        $content = file_get_contents($file);
        if (!$content) {
            return null;
        }
        $base = basename($file, '.php');
        if (preg_match('/^\s*namespace\s+([^;]+);/m', $content, $m)) {
            return trim($m[1]) . '\\' . $base;
        }
        return null;
    }

    /**
     * Detect the PHP namespace declared in any .php file directly inside $dir.
     * Returns an empty string if none is found.
     */
    public function detectNamespace(string $dir): string
    {
        foreach (glob(rtrim($dir, '/\\') . '/*.php') ?: [] as $file) {
            $code = @file_get_contents($file);
            if ($code && preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $code, $m)) {
                return $m[1];
            }
        }
        return '';
    }

    protected function discoverPath(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (glob($path . DIRECTORY_SEPARATOR . '*Task.php') ?: [] as $file) {
            $this->registerTaskFile($file);
        }
    }

    /**
     * Parse the namespace and class name directly from file content, then register the task if it is a valid Task subclass. This avoids any path/namespace guessing.
     */
    protected function registerTaskFile(string $file): void
    {
        $class = $this->extractClassFromFile($file);
        if (!$class) {
            return;
        }
        if (!class_exists($class)) {
            require_once $file;
        }
        if (class_exists($class) && is_subclass_of($class, Task::class)) {
            $taskName = $this->taskNameFromClass($class);
            if (!isset($this->tasks[$taskName])) {
                $this->tasks[$taskName] = $class;
                $this->taskClassFiles[$class] = $file;
            }
        }
    }

    protected function taskNameFromClass(string $class): string
    {
        $short = (new ReflectionClass($class))->getShortName();
        $short = preg_replace('/Task$/', '', $short);
        $parts = preg_split('/(?=[A-Z])/', $short, -1, PREG_SPLIT_NO_EMPTY);
        $parts = array_map(fn($p) => strtolower($p), $parts);
        return implode('-', $parts) ?: strtolower($short);
    }

    /** Built-in help task */
    public function helpOverview(): void
    {
        $this->writeln();
        $this->writeln("Usage: $this->scriptName <task> <action> [args...]");
        $this->writeln();
        $this->writeln($this->style('Available tasks and actions:', 'bold', 'white'));
        $termWidth = $this->terminalWidth();
        foreach ($this->tasks as $name => $class) {
            $actionDescriptions = $this->extractActionDescriptions($class);
            if (empty($actionDescriptions)) {
                continue; // skip tasks with no public actions
            }
            $this->writeln();
            $desc = $this->extractShortDescription($class);

            // Task label column
            $labelWidth = 22;
            $leftPad = 2; // leading spaces printed before label
            $avail = max(10, $termWidth - $leftPad - $labelWidth - 1);
            $descLines = $this->wrapText($desc, $avail);

            $labelStyled = $this->style(str_pad($name, $labelWidth), ...$this->taskStyles);
            if (count($descLines) > 0 && $descLines[0] !== '') {
                $this->writeln('  ' . $labelStyled . ' ' . $this->style($descLines[0], 'bold'));
            } else {
                $this->writeln('  ' . $labelStyled);
            }

            // remaining wrapped description lines (align under description column)
            for ($i = 1; $i < count($descLines); $i++) {
                $this->writeln('  ' . str_repeat(' ', $labelWidth) . ' ' . $this->style($descLines[$i], 'bold'));
            }

            // Actions: action column is printed with 4 leading spaces + 2 spaces before the name
            $actionLabelInner = 22; // base str_pad width used for actions
            $actionLeft = 2 + 2; // visual indent (leading spaces + inner padding)
            $defaultActionLabel = method_exists($class, $this->defaultAction)
                ? $this->methodToActionName($this->defaultAction)
                : null;
            foreach ($actionDescriptions as $action => $actionDesc) {
                // If an action name is longer than the base label width, account
                // for its actual length when calculating description wrap width
                $labelWidth = max($actionLabelInner, strlen($action));
                // Reserve two spaces between action label and description
                $actionAvail = max(10, $termWidth - $actionLeft - $labelWidth - 2);

                $defaultMarker = $action === $defaultActionLabel
                    ? ' ' . $this->style('[default]', ...$this->muteStyles)
                    : '';
                if ($actionDesc === '') {
                    $this->writeln(
                        '    '
                        . $this->style('  ' . str_pad($action, $labelWidth), ...$this->actionStyles)
                        . $defaultMarker
                    );
                    continue;
                }

                $actionLines = $this->wrapText($actionDesc, $actionAvail);
                $first = array_shift($actionLines);
                $this->writeln(
                    '  '
                    . $this->style('  ' . str_pad($action, $labelWidth), ...$this->actionStyles)
                    . '  ' . $this->style($first)
                    . $defaultMarker
                );
                foreach ($actionLines as $ln) {
                    $this->writeln('  ' . str_repeat(' ', $labelWidth + 2) . '  ' . $this->style($ln));
                }
            }
        }
        $this->writeln();
        $this->writeln($this->style('Run "' . $this->scriptName . ' help <task>" for details.'));

        /*
        if ($this->globalHelp !== null) {
            $this->writeln();
            $this->renderGlobalHelp($this->terminalWidth());
        }
        */

        $this->writeln();
    }

    /**
     * Built-in help task for a specific task
     * @param string $task The name of the task to display help for
     */
    public function helpTask(string $task): void
    {
        $taskKey = strtolower($task);
        $class = $this->tasks[$taskKey] ?? null;
        if (!$class) {
            $this->writeln("Task '{$task}' not found.");
            return;
        }

        $termWidth = $this->terminalWidth();
        $sectionStyles = $this->sectionStyles;

        $ref = new ReflectionClass($class);
        $doc = $ref->getDocComment() ?: '';
        $info = static::parseDocComment($doc, $this->scriptName);

        $this->writeln();
        $this->writeln($this->style('Task: ', ...$sectionStyles) . $this->style($taskKey, ...$this->taskStyles));
        //$this->writeln('      ' . $this->style(str_repeat('─', strlen($taskKey)), 'cyan'));
        $this->writeln();
        $this->writeln($this->style($info['description'], 'bold', 'white'));
        $this->writeln();

        // list available actions
        $actions = $this->extractActionDescriptions($class);

        if (!empty($actions)) {
            $this->writeln($this->style('Actions:', ...$sectionStyles));

            $actionLabelInner = 16;
            $leadingSpaces = 2; // two leading spaces before task
            // description starts after: leading + task + ' ' + actionLabel + '  '
            // reserve two spaces between action and description
            $descStartCol = $leadingSpaces + $actionLabelInner + 2;
            $actionAvail = max(10, $termWidth - $descStartCol);
            $defaultActionLabel = method_exists($class, $this->defaultAction)
                ? $this->methodToActionName($this->defaultAction)
                : null;

            foreach ($actions as $action => $actionDesc) {
                $defaultMarker = $action === $defaultActionLabel
                    ? ' ' . $this->style('[default]', ...$this->muteStyles)
                    : '';
                $lines = $this->wrapText($actionDesc, $actionAvail);
                $first = array_shift($lines);

                $this->writeln(
                    str_repeat(' ', $leadingSpaces)
                    . $this->style(str_pad($action, $actionLabelInner), ...$this->actionStyles)
                    . ($first !== '' ? '  ' . $this->style($first) : '')
                    . $defaultMarker
                );

                // continuation lines: indent to description column
                $continuationIndent = str_repeat(' ', $descStartCol);
                foreach ($lines as $ln) {
                    $this->writeln($continuationIndent . $this->style($ln));
                }

            }
            $this->writeln();
        }

        $this->writeln($this->style('Usage:', ...$sectionStyles));
        $actionsList = implode('|', array_keys($actions)) ?: '<action>';
        $this->writeln('  ' . $this->style('php ' . $this->scriptName, ...$this->muteStyles) . ' ' . $this->style($taskKey, ...$this->taskStyles) . ' ' . $this->style($actionsList, ...$this->actionStyles) . ' [args...]');
        if ($info['usage']) {
            $this->renderUsageBlock($info['usage'], $taskKey, $termWidth);
        }
        $this->writeln();

        if ($info['options']) {
            $this->writeln($this->style('Options:', ...$sectionStyles));
            $this->renderOptionsBlock($info['options'], $termWidth);
            $this->writeln();
        }

        if ($info['examples']) {
            $this->writeln($this->style('Examples:', ...$sectionStyles));
            foreach (explode("\n", $info['examples']) as $l) {
                $this->writeln($this->highlightCommandLine($l, $taskKey));
            }
            $this->writeln();
        }

        // Global help: shown unless the task class opts out via $showGlobalHelp = false.
        if ($this->globalHelp !== null) {
            $showGlobal = true;
            if ($class && class_exists($class)) {
                $taskRef = new ReflectionClass($class);
                if ($taskRef->hasProperty('showGlobalHelp')) {
                    $prop = $taskRef->getProperty('showGlobalHelp');
                    $prop->setAccessible(true);
                    // Read default value from class definition (not from an instance)
                    $showGlobal = (bool) ($prop->hasDefaultValue() ? $prop->getDefaultValue() : true);
                }
            }
            if ($showGlobal) {
                $this->renderGlobalHelp();
            }
        }
    }

    /**
     * Syntax-highlight a single command line in a Usage or Examples block.
     *
     * Token colouring rules:
     *   interpreter (php)   → dim
     *   script name         → dim
     *   task name           → bold white
     *   action name         → bold cyan
     *   <placeholder>       → bright yellow
     *   [--option]          → green brackets, highlighted inner token
     *   --flag / --key=val  → green (val placeholder stays bright yellow)
     *   # comment           → gray
     *   positional arg      → white
     *   continuation lines  → only option/arg tokens (no interpreter prefix)
     */
    protected function highlightCommandLine(string $line, ?string $taskName = null): string
    {
        if (!$this->useColors) {
            return $line;
        }

        // Strip trailing CR/LF so Windows \r doesn't corrupt the last token
        $line = rtrim($line);

        // Preserve leading indentation
        $trimmed = ltrim($line);
        $indent = substr($line, 0, strlen($line) - strlen($trimmed));

        if ($trimmed === '') {
            return $line;
        }

        // Split into (word, whitespace, word, whitespace …) keeping delimiters
        $parts = preg_split('/( +)/', $trimmed, -1, PREG_SPLIT_DELIM_CAPTURE);

        // Detect whether this is a command line (starts with interpreter) or a
        // continuation line (starts with options / placeholders)
        $firstWord = $parts[0] ?? '';
        $isCommand = (bool) preg_match('/^php\d*(?:\.exe)?$/i', $firstWord);

        $result = $indent;
        $wordIndex = 0; // counts only non-whitespace tokens
        $inComment = false;

        foreach ($parts as $part) {
            // Whitespace between tokens – pass through unchanged
            if ($part !== '' && $part[0] === ' ') {
                $result .= $part;
                continue;
            }

            if ($inComment) {
                $result .= $this->style($part, ...$this->commentStyles);
                continue;
            }

            if ($part === '') {
                continue;
            }

            // Comment marker
            if ($part[0] === '#') {
                $inComment = true;
                $result .= $this->style($part, ...$this->commentStyles);
                $wordIndex++;
                continue;
            }

            if ($isCommand) {
                $result .= match ($wordIndex) {
                    0 => $this->style($part, ...$this->muteStyles),                   // php
                    1 => $this->style($part, ...$this->muteStyles),                   // script
                    2 => $this->style($part, ...$this->taskStyles),         // task
                    3 => $this->style($part, ...$this->actionStyles),         // action
                    default => $this->highlightCliToken($part),
                };
            } else {
                $result .= $this->highlightCliToken($part);
            }

            $wordIndex++;
        }

        return $result;
    }

    /**
     * Colour a single CLI token: option, placeholder, or positional argument.
     */
    protected function highlightCliToken(string $token): string
    {
        if ($token === '') {
            return '';
        }

        // [--option], [--key=<val>], [<placeholder>] …
        if ($token[0] === '[' && str_ends_with($token, ']')) {
            $inner = substr($token, 1, -1);
            return $this->style('[', ...$this->braceStyles)
                . $this->highlightCliToken($inner)
                . $this->style(']', ...$this->braceStyles);
        }

        // <placeholder> or <a|b|c>
        if ($token[0] === '<' && str_ends_with($token, '>')) {
            return $this->style($token, ...$this->requiredArgStyles);
        }

        // --flag or --key=<val> or --key=literal
        if (str_starts_with($token, '--') || (strlen($token) === 2 && $token[0] === '-')) {
            if (str_contains($token, '=')) {
                [$flag, $val] = explode('=', $token, 2);
                $coloredVal = ($val !== '' && $val[0] === '<')
                    ? $this->style($val, ...$this->requiredArgStyles)
                    : $this->style($val, 'white');
                return $this->style($flag, ...$this->optionStyles) . $this->style('=', 'gray') . $coloredVal;
            }
            return $this->style($token, ...$this->optionStyles);
        }

        // short option -f
        if (strlen($token) >= 2 && $token[0] === '-') {
            return $this->style($token, ...$this->optionStyles);
        }

        // Plain positional argument (e.g. src/Models, User.php)
        return $this->style($token, 'white');
    }

    protected function methodToActionName(string $method): string
    {
        $base = preg_replace('/Action$/', '', $method);
        // convert camelCase to dashed
        $dashed = strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $base));
        return $dashed;
    }

    protected function extractShortDescription(?string $class): string
    {
        if (!$class || !class_exists($class)) {
            return '';
        }
        $ref = new ReflectionClass($class);
        $doc = $ref->getDocComment() ?: '';
        $info = static::parseDocComment($doc, $this->scriptName);
        return $info['description'] ? strtok($info['description'], "\n") : '';
    }

    /**
     * Returns an ordered map of action-name => one-line description for all
     * public *Action methods on the given class. Lines starting with @param
     * (and everything after) are stripped; only the opening prose is kept.
     *
     * @return array<string, string>
     */
    protected function extractActionDescriptions(string $class): array
    {
        if (!class_exists($class)) {
            return [];
        }
        $ref = new ReflectionClass($class);
        $actions = [];
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            $name = $m->getName();
            if (!str_ends_with($name, 'Action')) {
                continue;
            }
            if (isset(static::RESERVED_ACTIONS[$name])) {
                continue;
            }
            $actionName = $this->methodToActionName($name);
            $doc = $m->getDocComment() ?: '';
            $actions[$actionName] = $this->extractDocHeader($doc);
        }
        return $actions;
    }

    /**
     * Extract the opening prose from a doc comment, stopping at the first
     * @-tag line. All consecutive prose lines are joined into one
     * string so wrapped sentences come out complete.
     */
    protected function extractDocHeader(string $doc): string
    {
        if ($doc === '') {
            return '';
        }
        // Strip /** ... */ wrapper and leading " * "
        $doc = trim(preg_replace('/^\/\*\*|\*\/$/', '', $doc));
        $doc = preg_replace('/^\s*\*\s?/m', '', $doc);

        $parts = [];
        foreach (explode("\n", $doc) as $line) {
            $line = rtrim($line, "\r");
            $trim = trim($line);
            if ($trim === '' || $trim[0] === '@') {
                break; // stop at blank line or first @tag
            }
            $parts[] = $trim;
        }
        return implode(' ', $parts);
    }

    protected function registerSimpleAutoload(string $path): void
    {
        spl_autoload_register(function ($class) use ($path) {
            $parts = explode('\\', $class);
            $file = $path . DIRECTORY_SEPARATOR . end($parts) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }

    protected function splitArgs(array $args): array
    {
        $options = [];
        $params = [];

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
        'true' => true,
        'on' => true,
        'false' => false,
        'off' => false,
        'null' => null,
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

    protected static function parseDocComment(string $doc, string $scriptName): array
    {
        $doc = trim(preg_replace('/^\/\*\*|\*\/$/', '', $doc));
        $doc = preg_replace('/^\s*\*\s?/m', '', $doc);
        $doc = preg_replace('/^\s*[\w-]+\.php/', $scriptName, $doc);
        $doc = str_replace('console.php', $scriptName, $doc);
        $sections = ['description' => '', 'usage' => '', 'options' => '', 'examples' => '',];
        $current = 'description';
        $doc = str_replace("\r", '', $doc);
        foreach (explode("\n", $doc) as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $sections[$current] .= "\n";
                continue;
            }
            if (stripos($trim, 'Usage:') === 0) {
                $current = 'usage';
                continue;
            }
            if (stripos($trim, 'Options:') === 0) {
                $current = 'options';
                continue;
            }
            if (stripos($trim, 'Examples:') === 0) {
                $current = 'examples';
                continue;
            }
            if ($current === 'description') {
                $line .= " ";
            } else {
                $line .= "\n";
            }
            $sections[$current] .= $line;
        }
        foreach ($sections as $key => $s) {
            $sections[$key] = rtrim($s);
        }
        return $sections;
    }

    /**
     * Return detected terminal width (columns). Falls back to 80.
     */
    public function terminalWidth(): int
    {
        static $w = null;
        if ($w !== null) {
            return $w;
        }

        $default = 80;

        // 1) ENV
        $cols = getenv('COLUMNS');
        if ($cols !== false && (int) $cols > 0) {
            $w = (int) $cols;
            return $w;
        }

        // 2) tput (Unix)
        if (function_exists('exec')) {
            // only try tput when we are in a real terminal
            if (function_exists('posix_isatty') && @posix_isatty(STDOUT)) {
                $out = [];
                @exec('tput cols 2>/dev/null', $out);
                if (!empty($out) && is_array($out) && (int) $out[0] > 0) {
                    $w = (int) $out[0];
                    return $w;
                }
            }

            // 3) Windows: try to parse "mode CON" output for "Columns: N"
            if (stripos(PHP_OS, 'WIN') === 0) {
                $out = [];
                @exec('mode CON 2>&1', $out);
                if (!empty($out) && is_array($out)) {
                    $columnPos = 1; // usually the second number in the line "Columns: 120"
                    foreach ($out as $line) {
                        if (preg_match('/\b(\d{2,4})\b/', $line, $m)) {
                            if ($columnPos-- > 0) {
                                continue; // skip until we reach the column number
                            }
                            $w = (int) $m[1];
                            return $w;
                        }
                    }
                }
            }
        }

        $w = $default;
        return $w;
    }


    /**
     * Parse and render the global help text, which may contain multiple named sections.
     *
     * Section headers are lines matching "Word(s):" (e.g. "Options:", "Notes:", "Examples:").
     * Any text before the first header is rendered as plain prose.
     *
     * Section rendering by label (case-insensitive):
     *  - "Options"  → renderOptionsBlock()  – aligned flag + description columns, syntax-highlighted tokens
     *  - "Examples" → highlightCommandLine() – one highlighted command per line
     *  - "Usage"    → highlightCommandLine() – treated like examples (no task-name context)
     *  - Anything else (Notes, Warning, Info …) → word-wrapped plain text in muted color
     */
    protected function renderGlobalHelp(): void
    {
        if ($this->globalHelp === null) {
            return;
        }

        $termWidth = $this->terminalWidth();

        // ── Split into sections ──────────────────────────────────────────────
        // A header is a line whose trimmed form looks like "Word(s):" with nothing after the colon.
        $headerPattern = '/^([A-Za-z][A-Za-z\s]*):\s*$/';

        $sections = [];          // [['label' => string|null, 'lines' => string[]]]
        $current = ['label' => null, 'lines' => []];

        foreach (explode("\n", str_replace("\r", '', $this->globalHelp)) as $line) {
            if (preg_match($headerPattern, rtrim($line), $m)) {
                if ($current['label'] !== null || !empty($current['lines'])) {
                    $sections[] = $current;
                }
                $current = ['label' => trim($m[1]), 'lines' => []];
            } else {
                $current['lines'][] = $line;
            }
        }
        if ($current['label'] !== null || !empty($current['lines'])) {
            $sections[] = $current;
        }

        // ── Render each section ──────────────────────────────────────────────
        foreach ($sections as $section) {
            $label = $section['label'];
            $body = implode("\n", $section['lines']);
            $bodyTrimmed = trim($body);

            if ($label !== null) {
                $this->writeln($this->style($label . ':', ...$this->sectionStyles));
            }

            if ($bodyTrimmed === '') {
                if ($label !== null) {
                    $this->writeln();
                }
                continue;
            }

            $labelLower = strtolower($label ?? '');

            if ($labelLower === 'options' || str_ends_with($labelLower, ' options')) {
                // Options block: flag + description columns, syntax-highlighted
                $this->renderOptionsBlock($body, $termWidth);
                $this->writeln();
            } elseif ($labelLower === 'examples' || $labelLower === 'usage') {
                // Command lines with syntax highlighting
                foreach (explode("\n", $body) as $l) {
                    $this->writeln($this->highlightCommandLine($l));
                }
                $this->writeln();
            } else {
                // Prose / notes: word-wrap in muted color, 2-space indent
                foreach (explode("\n", $body) as $l) {
                    $trimmed = trim($l);
                    if ($trimmed === '') {
                        $this->writeln();
                        continue;
                    }
                    foreach ($this->wrapText($trimmed, max(10, $termWidth - 2)) as $wrapped) {
                        $this->writeln('  ' . $this->style($wrapped, ...$this->commentStyles));
                    }
                }
                $this->writeln();
            }
        }
    }

    /**
     * Parse and render a Usage block:
     *  - Lines starting with 'php' or the task name open a new usage entry.
     *  - Lines starting with '[', '<', or '-' are continuations of the current entry.
     *  - All other non-blank lines are treated as prose and rendered inline.
     *  - Argument columns across all entries are aligned to the longest left side.
     */
    protected function renderUsageBlock(string $usageText, string $taskKey, int $termWidth): void
    {
        $taskPattern = '/^' . preg_quote($taskKey, '/') . '\b/i';

        // ── Pass 1: split into 'entry' and 'prose' items ─────────────────────
        $items = [];
        $currentEntry = null;
        $emptyLine = false;

        foreach (explode("\n", $usageText) as $ln) {
            $ln = rtrim($ln);
            $trim = ltrim($ln);

            if ($trim === '') {
                if ($currentEntry !== null) {
                    $items[] = ['type' => 'entry', 'text' => $currentEntry];
                    $currentEntry = null;
                }
                $emptyLine = true;
                continue;
            }

            $trim = preg_replace('/^php\d*(?:\.exe)?\b\s*/i', '', $trim);
            $trim = preg_replace('/^\w+\.php\b\s*/i', '', $trim);
            $isEntryStart = (bool) preg_match('/^php\d*(?:\.exe)?\b/i', $trim)
                || (bool) preg_match($taskPattern, $trim);
            $isContinuation = (bool) preg_match('/^[\[<\-]/', $trim);

            if ($isEntryStart) {
                if ($currentEntry !== null) {
                    $items[] = ['type' => 'entry', 'text' => $currentEntry];
                }
                $currentEntry = $trim;
            } elseif ($isContinuation && $currentEntry !== null) {
                $currentEntry .= ' ' . $trim;
            } else {
                if ($currentEntry !== null) {
                    $items[] = ['type' => 'entry', 'text' => $currentEntry];
                    $currentEntry = null;
                }
                if (!$emptyLine && !empty($items) && end($items)['type'] === 'prose') {
                    // append to previous prose block
                    $items[count($items) - 1]['text'] .= ' ' . $trim;
                } else {
                    $items[] = ['type' => 'prose', 'text' => $trim];
                }
            }
            $emptyLine = false;
        }
        if ($currentEntry !== null) {
            $items[] = ['type' => 'entry', 'text' => $currentEntry];
        }

        // ── Pass 2: parse each entry, find max left-column width ─────────────
        $maxLeftLen = 0;
        foreach ($items as &$item) {
            if ($item['type'] !== 'entry') {
                continue;
            }
            $parts = preg_split('/\s+/', $item['text']);
            $isPhp = (bool) preg_match('/^php\d*(?:\.exe)?$/i', $parts[0]);
            $actionIdx = $isPhp ? 3 : 1;
            $actionIdx = min($actionIdx, count($parts) - 1);
            $leftParts = array_slice($parts, 0, $actionIdx + 1);
            $restParts = array_slice($parts, $actionIdx + 1);
            $leftPlain = implode(' ', $leftParts);

            $item['isPhp'] = $isPhp;
            $item['leftParts'] = $leftParts;
            $item['leftPlain'] = $leftPlain;
            $item['rest'] = implode(' ', $restParts);

            $maxLeftLen = max($maxLeftLen, strlen($leftPlain));
        }
        unset($item);

        // ── Pass 3: render ───────────────────────────────────────────────────
        $leadingSpaces = 2;
        $descStartCol = $leadingSpaces + $maxLeftLen + 1;
        $argAvail = max(10, $termWidth - $descStartCol);
        $contIndent = str_repeat(' ', $descStartCol);
        $addEmptyLine = true;

        foreach ($items as $item) {

            if ($item['type'] === 'prose') {
                if ($addEmptyLine) {
                    $this->writeln();
                    $addEmptyLine = false;
                }
                $this->writeln(str_repeat(' ', $leadingSpaces) . $this->style($item['text'], ...$this->commentStyles));
                continue;
            }

            $addEmptyLine = true;
            $this->writeln();

            // Style left tokens
            $leftStyled = [];
            foreach ($item['leftParts'] as $i => $tok) {
                if ($item['isPhp']) {
                    $leftStyled[] = match ($i) {
                        0, 1 => $this->style($tok, ...$this->muteStyles),
                        2 => $this->style($tok, ...$this->taskStyles),
                        default => $this->style($tok, ...$this->actionStyles),
                    };
                } else {
                    $leftStyled[] = $i === 0
                        ? $this->style($tok, ...$this->taskStyles)
                        : $this->style($tok, ...$this->actionStyles);
                }
            }

            // Pad left side so all args start at the same column
            $padding = str_repeat(' ', $maxLeftLen - strlen($item['leftPlain']));
            $argLines = $this->wrapText($item['rest'], $argAvail);
            $firstArg = array_shift($argLines);

            $this->writeln(
                str_repeat(' ', $leadingSpaces)
                . implode(' ', $leftStyled)
                . $padding
                . ($firstArg !== '' ? ' ' . $this->highlightCommandLine($firstArg) : '')
            );
            foreach ($argLines as $al) {
                $this->writeln($contIndent . $this->highlightCommandLine($al));
            }
        }
    }

    /**
     * Parse and render an Options block:
     *  - Lines starting with '-' open a new option entry (token + description split at 2+ spaces).
     *  - All other non-blank lines are treated as continuation text of the current option.
     *  - All option names are left-aligned to the longest token; descriptions are word-wrapped.
     *  - The option token is coloured via highlightCliToken(); descriptions are rendered in gray.
     */
    protected function renderOptionsBlock(string $optionsText, int $termWidth): void
    {
        // ── Pass 1: parse into token => full-description pairs ───────────────
        $options = [];
        $currentToken = null;

        foreach (explode("\n", $optionsText) as $line) {
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }
            if (str_starts_with($trim, '-')) {
                // New option line – split at first run of 2+ spaces
                $parts = preg_split('/\s{2,}/', $trim, 2);
                $currentToken = $parts[0];
                $options[$currentToken] = isset($parts[1]) ? trim($parts[1]) : '';
            } elseif ($currentToken !== null) {
                // Continuation line – join into description
                $options[$currentToken] .= ' ' . $trim;
            }
        }

        if (empty($options)) {
            return;
        }

        // ── Pass 2: find max token length for alignment ──────────────────────
        $maxTokenLen = 0;
        foreach (array_keys($options) as $token) {
            $maxTokenLen = max($maxTokenLen, strlen($token));
        }

        // ── Pass 3: render ───────────────────────────────────────────────────
        $leadingSpaces = 2;
        $gap = 2;
        $descStartCol = $leadingSpaces + $maxTokenLen + $gap;
        $descAvail = max(10, $termWidth - $descStartCol);
        $contIndent = str_repeat(' ', $descStartCol);

        foreach ($options as $token => $description) {
            $coloredToken = $this->highlightCliToken($token);
            $padding = str_repeat(' ', $maxTokenLen - strlen($token) + $gap);

            if ($description === '') {
                $this->writeln(str_repeat(' ', $leadingSpaces) . $coloredToken);
                continue;
            }

            $lines = $this->wrapText($description, $descAvail);
            $first = array_shift($lines);
            $this->writeln(
                str_repeat(' ', $leadingSpaces)
                . $coloredToken
                . $padding
                . $this->style($first, 'white')
            );
            foreach ($lines as $ln) {
                $this->writeln($contIndent . $this->style($ln, 'white'));
            }
        }
    }

    /**
     * Word-wrap a text block into an array of lines for the given column width.
     * Lines are trimmed of trailing whitespace. Empty input returns an array with one empty string.
     * @param string $text The text to wrap.
     * @param int $width The maximum column width for wrapping.
     */
    public function wrapText(string $text, int $width): array
    {
        $width = max(10, (int) $width);
        if ($text === '') {
            return [''];
        }
        $wrapped = wordwrap($text, $width, "\n");
        $lines = explode("\n", $wrapped);
        return array_map(fn($l) => rtrim($l), $lines);
    }

}
