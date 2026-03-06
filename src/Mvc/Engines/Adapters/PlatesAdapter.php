<?php
namespace Merlin\Mvc\Engines\Adapters;

use Merlin\Mvc\ViewEngine;

class PlatesAdapter extends ViewEngine
{
    protected $plates;
    protected bool $autoEscape = false;

    public function __construct(array $vars = [])
    {
        parent::__construct($vars);
        $this->extension = '.php';
    }

    protected function ensurePlates(): void
    {
        if (isset($this->plates)) {
            return;
        }
        if (!class_exists('\League\Plates\Engine')) {
            throw new \RuntimeException('Plates not installed. Run: composer require --dev league/plates');
        }
        $this->plates = new \League\Plates\Engine($this->viewPath);
    }

    public function render(string $view, array $vars = []): string
    {
        $this->ensurePlates();
        $name = basename($this->resolveView($view), $this->extension);
        $merged = array_merge($this->vars, $vars);
        return $this->plates->render($name, $merged);
    }

    public function renderPartial(string $view, array $vars = []): string
    {
        return $this->render($view, $vars);
    }

    public function renderLayout(string $layout, string $content, array $vars = []): string
    {
        $vars = array_merge($this->vars, $vars, ['content' => $content]);
        return $this->render($layout, $vars);
    }
}
