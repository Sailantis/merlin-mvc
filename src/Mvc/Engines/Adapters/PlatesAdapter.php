<?php
namespace Merlin\Mvc\Engines\Adapters;

use Merlin\Mvc\ViewEngine;

/**
 * Plates template engine adapter.
 *
 * Wraps League/Plates so Merlin applications can use `.plates.php` templates.
 * Requires `league/plates` to be installed:
 *
 * ```sh
 * composer require league/plates
 * ```
 *
 * Plates does not use a disk cache; compiled output is plain PHP that the
 * PHP runtime (and OPcache) handle directly.
 *
 * Filters are mapped to Plates *template functions*, which are called inside
 * templates as `$this->filterName($value)`.
 */
class PlatesAdapter extends ViewEngine
{
    protected string $extension = '.plates.php';

    protected \League\Plates\Engine $plates;

    // -------------------------------------------------------------------------
    // Namespaces
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * Also registers the namespace as a Plates folder so templates can use
     * `namespace::view` syntax.
     */
    public function addNamespace(string $name, string $path): static
    {
        parent::addNamespace($name, $path);
        if (isset($this->plates)) {
            $this->plates->addFolder($name, $path);
        }
        return $this;
    }

    // -------------------------------------------------------------------------
    // Filters and functions (both map to Plates template functions)
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * Plates does not distinguish between filters and functions; both are
     * registered as Plates *template functions* and called inside templates as
     * `$this->name($value, ...$args)`.  This method delegates to
     * {@see addFunction()}.
     */
    public function addFilter(string $name, callable $fn): static
    {
        return $this->addFunction($name, $fn);
    }

    /**
     * {@inheritdoc}
     *
     * Registers a Plates template function, callable inside templates as
     * `$this->name($arg1, $arg2)`.
     *
     * Plates does not distinguish between filters and functions at the API
     * level; `addFilter()` is an alias for this method.
     */
    public function addFunction(string $name, callable $fn): static
    {
        $this->ensurePlates();
        $this->plates->registerFunction($name, $fn);
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Returns the underlying `\League\Plates\Engine` instance for advanced
     * configuration (extensions, data, etc.).
     * Initialises Plates on first call if not already done.
     */
    public function getDriver(): mixed
    {
        $this->ensurePlates();
        return $this->plates;
    }

    // -------------------------------------------------------------------------
    // Internal setup
    // -------------------------------------------------------------------------

    protected function ensurePlates(): void
    {
        if (isset($this->plates)) {
            return;
        }
        if (!class_exists('\League\Plates\Engine')) {
            throw new \RuntimeException(
                'Plates not installed. Run: composer require league/plates'
            );
        }
        $this->plates = new \League\Plates\Engine(
            $this->viewPath,
            ltrim($this->extension, '.')
        );
        foreach ($this->namespaces as $ns => $path) {
            $this->plates->addFolder($ns, $path);
        }
    }

    // -------------------------------------------------------------------------
    // View name conversion
    // -------------------------------------------------------------------------

    /**
     * Convert a Merlin view name to Plates format.
     *
     * Plates uses `/` as the directory separator and `folder::template` for
     * namespaced views.  Dot-notation is converted to slashes.
     *
     * | Merlin              | Plates              |
     * |---------------------|---------------------|
     * | `home/index`        | `home/index`        |
     * | `home.index`        | `home/index`        |
     * | `admin::dashboard`  | `admin::dashboard`  |
     * | `admin::home.dash`  | `admin::home/dash`  |
     */
    protected function viewNameToPlates(string $view): string
    {
        $sep = strpos($view, '::');
        if ($sep !== false) {
            $ns = substr($view, 0, $sep);
            $name = substr($view, $sep + 2);
            return $ns . '::' . str_replace('.', '/', $name);
        }
        return str_replace('.', '/', ltrim($view, '/'));
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
        $this->ensurePlates();
        $name = $this->viewNameToPlates($view);
        $merged = [...$this->vars, ...$vars];
        $this->renderDepth++;
        try {
            return $this->plates->render($name, $merged);
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
