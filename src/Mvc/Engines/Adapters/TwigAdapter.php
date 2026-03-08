<?php
namespace Merlin\Mvc\Engines\Adapters;

use Merlin\Mvc\ViewEngine;

/**
 * Twig template engine adapter.
 *
 * Wraps Twig/Twig so Merlin applications can use `.twig` templates.
 * Requires `twig/twig` to be installed:
 *
 * ```sh
 * composer require twig/twig
 * ```
 *
 * Twig filters are registered natively and are available in templates using
 * the pipe syntax: `{{ value|filterName }}`.
 *
 * Cache location: `sys_get_temp_dir()/twig_cache` (override with {@see setCachePath()}).
 * Pass an empty string to disable caching.
 */
class TwigAdapter extends ViewEngine
{
    protected string $extension = '.twig';
    protected string $cachePath;

    /** Filters registered before Twig is initialised. */
    protected array $pendingFilters = [];

    /** Functions registered before Twig is initialised. */
    protected array $pendingFunctions = [];

    protected \Twig\Environment $twig;

    public function __construct(array $vars = [])
    {
        parent::__construct($vars);
        $this->cachePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'twig_cache';
    }

    // -------------------------------------------------------------------------
    // Cache configuration
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * Pass an empty string to disable caching entirely.
     * Changes take effect immediately even if Twig is already initialised.
     */
    public function setCachePath(string $path): static
    {
        $this->cachePath = $path;
        if (isset($this->twig)) {
            $this->twig->setCache(
                $path !== '' ? new \Twig\Cache\FilesystemCache($path) : false
            );
        }
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
     * Also registers the namespace with the Twig FilesystemLoader so templates
     * can reference it as `@namespace/path/to/template.twig`.  If Twig has not
     * been initialised yet the namespace is queued and applied on first render.
     */
    public function addNamespace(string $name, string $path): static
    {
        parent::addNamespace($name, $path);
        if (isset($this->twig)) {
            $loader = $this->twig->getLoader();
            if ($loader instanceof \Twig\Loader\FilesystemLoader) {
                $loader->addPath($path, $name);
            }
        }
        return $this;
    }

    // -------------------------------------------------------------------------
    // Filters and functions
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * Registers a Twig filter callable.  Available in templates as
     * `{{ value|name }}` or `{{ value|name(arg1, arg2) }}`.
     *
     * Can be called before or after the first render.
     */
    public function addFilter(string $name, callable $fn): static
    {
        if (isset($this->twig)) {
            $this->twig->addFilter(new \Twig\TwigFilter($name, $fn));
        } else {
            $this->pendingFilters[$name] = $fn;
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Registers a Twig function callable.  Available in templates as
     * `{{ name(arg1, arg2) }}`.
     *
     * Can be called before or after the first render.
     */
    public function addFunction(string $name, callable $fn): static
    {
        if (isset($this->twig)) {
            $this->twig->addFunction(new \Twig\TwigFunction($name, $fn));
        } else {
            $this->pendingFunctions[$name] = $fn;
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Returns the underlying `\Twig\Environment` instance for advanced
     * configuration (extensions, token parsers, globals, etc.).
     * Initialises Twig on first call if not already done.
     */
    public function getDriver(): mixed
    {
        $this->ensureTwig();
        return $this->twig;
    }

    // -------------------------------------------------------------------------
    // Internal setup
    // -------------------------------------------------------------------------

    protected function ensureTwig(): void
    {
        if (isset($this->twig)) {
            return;
        }
        if (!class_exists('\Twig\Environment')) {
            throw new \RuntimeException(
                'Twig not installed. Run: composer require twig/twig'
            );
        }
        $loader = new \Twig\Loader\FilesystemLoader($this->viewPath);
        foreach ($this->namespaces as $ns => $path) {
            $loader->addPath($path, $ns);
        }
        $this->twig = new \Twig\Environment($loader, [
            'cache' => $this->cachePath !== '' ? $this->cachePath : false,
            'auto_reload' => true,
        ]);
        foreach ($this->pendingFilters as $name => $fn) {
            $this->twig->addFilter(new \Twig\TwigFilter($name, $fn));
        }
        $this->pendingFilters = [];
        foreach ($this->pendingFunctions as $name => $fn) {
            $this->twig->addFunction(new \Twig\TwigFunction($name, $fn));
        }
        $this->pendingFunctions = [];
    }

    // -------------------------------------------------------------------------
    // View name conversion
    // -------------------------------------------------------------------------

    /**
     * Convert a Merlin view name to a Twig template name.
     *
     * Twig uses `/` as the directory separator and `@namespace/path` for
     * namespaced views.  Dot-notation is converted to slashes.
     *
     * | Merlin              | Twig                      |
     * |---------------------|---------------------------|
     * | `home/index`        | `home/index.twig`         |
     * | `home.index`        | `home/index.twig`         |
     * | `admin::dashboard`  | `@admin/dashboard.twig`   |
     * | `admin::home.dash`  | `@admin/home/dash.twig`   |
     */
    protected function viewNameToTwig(string $view): string
    {
        $sep = \strpos($view, '::');
        if ($sep !== false) {
            $ns = \substr($view, 0, $sep);
            $name = \substr($view, $sep + 2);
            $relative = \str_replace(['.', '\\'], '/', $name);
            $suffix = \str_ends_with($relative, $this->extension) ? '' : $this->extension;
            return '@' . $ns . '/' . $relative . $suffix;
        }
        $relative = \str_replace(['.', '\\'], '/', \ltrim($view, '/'));
        $suffix = \str_ends_with($relative, $this->extension) ? '' : $this->extension;
        return $relative . $suffix;
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
        $this->ensureTwig();
        $name = $this->viewNameToTwig($view);
        $merged = [...$this->vars, ...$vars];
        $this->renderDepth++;
        try {
            return $this->twig->render($name, $merged);
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
