<?php
namespace Merlin\Mvc\Engines;

use Merlin\Mvc\ViewEngine;

/**
 * Native PHP template engine.
 *
 * Templates are plain `.php` files. Variables are extracted into the local
 * scope and the file is included directly, making this engine as fast as
 * hand-written PHP includes.
 */
class NativeEngine extends ViewEngine
{
    protected string $extension = '.php';

    /**
     * Render a view (and optional layout) and return the result.
     *
     * @param string $view View name to render.
     * @param array $vars Additional variables for this render call.
     * @return string Rendered content.
     */
    public function render(string $view, array $vars = []): string
    {
        $content = $this->renderPartial($view, $vars);

        if ($this->layout !== null && $this->renderDepth === 0) {
            $content = $this->renderLayout($this->layout, $content, $vars);
        }

        return $content;
    }

    private $currentFile = null;

    /**
     * Render a partial view template and return the generated output.
     *
     * Variables are merged with global view variables and extracted into the
     * template scope. Per-call variables override globals.
     *
     * @param string $view View name to resolve and render.
     * @param array $vars Variables for this render call.
     * @return string Rendered HTML/output.
     * @throws \RuntimeException If the view file cannot be resolved.
     */
    public function renderPartial(string $view, array $vars = []): string
    {
        $this->currentFile = $this->resolveView($view);
        if (!is_file($this->currentFile)) {
            throw new \RuntimeException("View not found: {$this->currentFile}");
        }

        $this->renderDepth++;
        // Call-site vars override globals (unlike EXTR_SKIP which silently drops them)
        extract([...$this->vars, ...$vars]);

        ob_start();
        include $this->currentFile;
        $output = ob_get_clean();

        $this->renderDepth--;
        return $output;
    }

    /**
     * Render a layout template wrapping provided content.
     *
     * The layout receives the rendered view in the `content` variable.
     *
     * @param string $layout Layout view name.
     * @param string $content Previously rendered content.
     * @param array $vars Additional variables to pass to the layout.
     * @return string Rendered layout output.
     */
    public function renderLayout(string $layout, string $content, array $vars = []): string
    {
        $vars['content'] = $content;
        return $this->renderPartial($layout, $vars);
    }
}
