<?php

namespace Azera\Cli;

use Azera\AppContext;


/**
 * Base class for all CLI task classes.
 *
 * Extend this class to create a CLI task. Public methods ending in "Action"
 * are automatically discoverable by {@see Console}.
 */
abstract class Task
{
    /** @var Console The Console instance that is executing this task. */
    public Console $console;

    /** @var array<string, mixed> Parsed options from the command line. */
    public array $options = [];

    /**
     * Set to false in a subclass to suppress the global help section (registered via
     * {@see Console::setGlobalTaskHelp()}) when `php console help <this-task>` is run.
     */
    protected bool $showGlobalHelp = true;

    // -------------------------------------------------------------------------
    //  Output helpers – delegate to the Console for consistent color support
    // -------------------------------------------------------------------------

    /** Write text without a newline. */
    public function write(string $text = ''): void
    {
        $this->console->write($text);
    }

    /** Write a line of text with a newline. */
    public function writeln(string $text = ''): void
    {
        $this->console->writeln($text);
    }

    /** Write to STDERR without a newline. */
    public function stderr(string $text = ''): void
    {
        $this->console->stderr($text);
    }

    /** Write to STDERR with a newline. */
    public function stderrln(string $text = ''): void
    {
        $this->console->stderrln($text);
    }

    /** Write to STDOUT without a newline. */
    public function stdout(string $text = ''): void
    {
        $this->console->stdout($text);
    }

    /** Write to STDOUT with a newline. */
    public function stdoutln(string $text = ''): void
    {
        $this->console->stdoutln($text);
    }

    /** Plain message with no styling. Newline is appended. */
    public function line(string $text): void
    {
        $this->console->line($text);
    }

    /** Informational message (cyan). Newline is appended. */
    public function info(string $text): void
    {
        $this->console->info($text);
    }

    /** Success message (green). Newline is appended. */
    public function success(string $text): void
    {
        $this->console->success($text);
    }

    /** Warning message (yellow). Newline is appended. */
    public function warn(string $text): void
    {
        $this->console->warn($text);
    }

    /** Error message (white on red) to STDERR. Newline is appended. */
    public function error(string $text): void
    {
        $this->console->error($text);
    }

    /** Muted / dimmed text (gray). Newline is appended. */
    public function muted(string $text): void
    {
        $this->console->muted($text);
    }

    /**
     * Apply one or more named ANSI styles or custom colors to a string via the Console. (@see Console::style)
     * @param string $text The text to style.
     * @param string ...$styles One or more style names (e.g. "red", "bold") or custom colors (e.g. "#ff0000", "bg:#00ff00", "bg #00ff00").
     * @return string The styled text.
     */
    protected function style(string $text, string ...$styles): string
    {
        return $this->console->style($text, ...$styles);
    }

    // -------------------------------------------------------------------------
    //  Option and context helpers
    // -------------------------------------------------------------------------

    /** 
     * Retrieve a parsed option value by key, with an optional default.
     * @param string $key The option name (without leading dashes).
     * @param mixed $default The default value to return if the option is not set.
     * @return mixed The option value or the default if not set.
     */
    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Get the current AppContext instance. Useful for accessing services.
     * @return AppContext
     */
    public function context(): AppContext
    {
        return AppContext::instance();
    }

    // -------------------------------------------------------------------------
    //  Lifecycle hooks
    // -------------------------------------------------------------------------

    /**
     * Called before the action method is executed.
     * Override in a subclass to perform setup work (e.g. register event listeners based on options).
     * The method has access to $this->options and $this->console at this point.
     *
     * @param string $action The resolved PHP method name that will be invoked (e.g. "runAction").
     * @param array  $params Positional parameters that will be passed to the action.
     */
    public function beforeAction(string $action, array $params): void
    {
    }

    /**
     * Called after the action method has finished executing (including when an exception is thrown).
     * Override in a subclass to perform teardown or post-processing work (e.g. flush collected SQL logs).
     *
     * @param string $action The resolved PHP method name that was invoked (e.g. "runAction").
     * @param array  $params Positional parameters that were passed to the action.
     */
    public function afterAction(string $action, array $params): void
    {
    }

}
