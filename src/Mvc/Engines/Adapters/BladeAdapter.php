<?php
namespace Merlin\Mvc\Engines\Adapters;

use Merlin\Mvc\ViewEngine;

/**
 * Blade template engine adapter.
 *
 * Wraps Laravel's Illuminate/View Blade compiler so Merlin applications can
 * use `.blade.php` templates.  Requires `illuminate/view` to be installed:
 *
 * ```sh
 * composer require illuminate/view
 * ```
 *
 * Blade does **not** support pipe-style filters.  Use {@see addDirective()}
 * to register custom `@directiveName(...)` syntax instead.
 *
 * Cache location: `sys_get_temp_dir()/blade_cache` (override with {@see setCachePath()})
 */
class BladeAdapter extends ViewEngine
{
    protected string $extension = '.blade.php';
    protected string $cachePath;

    protected \Illuminate\View\Factory $factory;
    protected \Illuminate\View\FileViewFinder $finder;
    protected \Illuminate\View\Compilers\BladeCompiler $bladeCompiler;

    public function __construct(array $vars = [])
    {
        parent::__construct($vars);
        $this->cachePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'blade_cache';
    }

    // -------------------------------------------------------------------------
    // Cache configuration
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * Forces re-initialisation of the Blade compiler on the next render so the
     * new path takes effect.
     */
    public function setCachePath(string $path): static
    {
        $this->cachePath = $path;
        // Force re-initialisation so the new compiler picks up the new path.
        unset($this->factory, $this->finder, $this->bladeCompiler);
        return $this;
    }

    /** {@inheritdoc} */
    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    /** {@inheritdoc} */
    public function flushCache(): static
    {
        if (!is_dir($this->cachePath)) {
            return $this;
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cachePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        return $this;
    }

    // -------------------------------------------------------------------------
    // Namespaces
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * Also registers the namespace as a Blade hint path so templates can use
     * `namespace::view.name` syntax.
     */
    public function addNamespace(string $name, string $path): static
    {
        parent::addNamespace($name, $path);
        if (isset($this->finder)) {
            $this->finder->addNamespace($name, $path);
        }
        return $this;
    }

    // -------------------------------------------------------------------------
    // Directives / filters
    // -------------------------------------------------------------------------

    /**
     * Register a custom Blade directive.
     *
     * Blade does not support pipe-style filters; use this method to add
     * custom `@name(...)` syntax instead.
     *
     * @param string   $name    Directive name without the `@` prefix.
     * @param callable $handler fn(?string $expression): string — must return PHP code.
     * @return $this
     */
    public function addDirective(string $name, callable $handler): static
    {
        $this->ensureBlade();
        $this->bladeCompiler->directive($name, $handler);
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Blade does not support pipe-style filters.  Use {@see addDirective()}
     * to register a custom `@{$name}` directive instead.
     *
     * @throws \LogicException Always.
     */
    public function addFilter(string $name, callable $fn): static
    {
        throw new \LogicException(
            "BladeAdapter does not support pipe-style filters. "
            . "Use addDirective('{$name}', ...) to register a custom @{$name} directive."
        );
    }

    /**
     * {@inheritdoc}
     *
     * Blade does not have a standalone function concept equivalent to
     * Twig/Plates.  Use {@see addDirective()} to register a custom
     * `@{$name}(...)` directive instead.
     *
     * @throws \LogicException Always.
     */
    public function addFunction(string $name, callable $fn): static
    {
        throw new \LogicException(
            "BladeAdapter does not support template functions. "
            . "Use addDirective('{$name}', ...) to register a custom @{$name} directive."
        );
    }

    /**
     * {@inheritdoc}
     *
     * Returns the underlying `\Illuminate\View\Factory` instance for advanced
     * configuration.  Initialises Blade on first call if not already done.
     * Use `getDriver()->getEngineResolver()` or access `$this->bladeCompiler`
     * via {@see addDirective()} for compiler-level customisation.
     */
    public function getDriver(): mixed
    {
        $this->ensureBlade();
        return $this->factory;
    }

    // -------------------------------------------------------------------------
    // Internal setup
    // -------------------------------------------------------------------------

    protected function ensureBlade(): void
    {
        if (isset($this->factory)) {
            return;
        }
        if (!class_exists('\Illuminate\View\Factory')) {
            throw new \RuntimeException(
                'Illuminate/View not installed. Run: composer require illuminate/view'
            );
        }
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
        $filesystem = new \Illuminate\Filesystem\Filesystem();
        $this->bladeCompiler = new \Illuminate\View\Compilers\BladeCompiler($filesystem, $this->cachePath);
        $compiler = $this->bladeCompiler;
        $engineResolver = new \Illuminate\View\Engines\EngineResolver();
        $engineResolver->register('blade', static function () use ($compiler) {
            return new \Illuminate\View\Engines\CompilerEngine($compiler);
        });
        $this->finder = new \Illuminate\View\FileViewFinder($filesystem, [$this->viewPath]);
        foreach ($this->namespaces as $ns => $nsPath) {
            $this->finder->addNamespace($ns, $nsPath);
        }
        $this->factory = new \Illuminate\View\Factory(
            $engineResolver,
            $this->finder,
            new \Illuminate\Events\Dispatcher()
        );
    }

    // -------------------------------------------------------------------------
    // View name conversion
    // -------------------------------------------------------------------------

    /**
     * Convert a Merlin view name to Blade dot-notation.
     *
     * | Merlin              | Blade               |
     * |---------------------|---------------------|
     * | `home/index`        | `home.index`        |
     * | `home.index`        | `home.index`        |
     * | `admin::dashboard`  | `admin::dashboard`  |
     * | `admin::home/dash`  | `admin::home.dash`  |
     */
    protected function viewNameToBlade(string $view): string
    {
        $sep = strpos($view, '::');
        if ($sep !== false) {
            $ns = substr($view, 0, $sep);
            $name = substr($view, $sep + 2);
            return $ns . '::' . str_replace('/', '.', $name);
        }
        return str_replace('/', '.', ltrim($view, '/'));
    }

    // -------------------------------------------------------------------------
    // ViewEngine implementation
    // -------------------------------------------------------------------------

    /** {@inheritdoc} */
    public function render(string $view, array $vars = []): string
    {
        $content = $this->renderPartial($view, $vars);
        if ($this->layout !== null && $this->renderDepth === 0) {
            $content = $this->renderLayout($this->layout, $content, $vars);
        }
        return $content;
    }

    /** {@inheritdoc} */
    public function renderPartial(string $view, array $vars = []): string
    {
        $this->ensureBlade();
        $name = $this->viewNameToBlade($view);
        $merged = [...$this->vars, ...$vars];
        $this->renderDepth++;
        try {
            return $this->factory->make($name, $merged)->render();
        } finally {
            $this->renderDepth--;
        }
    }

    /** {@inheritdoc} */
    public function renderLayout(string $layout, string $content, array $vars = []): string
    {
        $vars['content'] = $content;
        return $this->renderPartial($layout, $vars);
    }
}
