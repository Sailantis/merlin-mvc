<?php

namespace Azera\Cli\Console;

use ReflectionClass;
use Azera\Cli\Task;

/**
 * Task autodiscovery: PSR-4 resolution, composer.json walking, and
 * filesystem scanning for task classes.
 */
trait TaskDiscovery
{
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
     * Explicitly set the composer/project root directory used for PSR-4
     * resolution and task autodiscovery. When set, this takes precedence
     * over the walk-up heuristic in findComposerRoot().
     *
     * Pass the project root directory (the folder containing composer.json)
     * so that task discovery scans the project's own autoload paths instead
     * of the framework's.
     *
     * @param string|null $dir Absolute path to the project root, or null to
     *                         fall back to automatic detection.
     */
    public function setComposerRoot(?string $dir): void
    {
        $this->composerRoot = $dir;
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
        $json   = json_decode(file_get_contents($composerDir . '/composer.json'), true);
        $raw    = $json['autoload']['psr-4'] ?? [];
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

        // An explicitly provided project root always wins. This lets a bin
        // script that already located the project (e.g. by walking up from
        // CWD) point discovery at the project's composer.json instead of the
        // framework's own.
        if ($this->composerRoot !== null && is_file($this->composerRoot . '/composer.json')) {
            return $cache = $this->composerRoot;
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
        $map        = $this->readComposerPsr4();
        $nsClean    = rtrim($namespace, '\\');
        $bestPrefix = null;
        $bestDir    = null;
        foreach ($map as $prefix => $dir) {
            $prefixClean = rtrim($prefix, '\\');
            if ($nsClean === $prefixClean || str_starts_with($nsClean . '\\', $prefixClean . '\\')) {
                if ($bestPrefix === null || strlen($prefixClean) > strlen($bestPrefix)) {
                    $bestPrefix = $prefixClean;
                    $bestDir    = $dir;
                }
            }
        }
        if ($bestPrefix !== null) {
            $suffix = ltrim(substr($nsClean, strlen($bestPrefix)), '\\');
            $path   = $suffix
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
            $this->tasks[$taskName] = $class;
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

    protected function registerSimpleAutoload(string $path): void
    {
        spl_autoload_register(
            function ($class) use ($path) {
                $parts = explode('\\', $class);
                $file  = $path . DIRECTORY_SEPARATOR . end($parts) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        );
    }
}