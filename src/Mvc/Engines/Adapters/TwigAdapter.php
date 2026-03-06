<?php
namespace Merlin\Mvc\Engines\Adapters;

use Merlin\Mvc\ViewEngine;

class TwigAdapter extends ViewEngine
{
    protected \Twig\Environment $twig;

    public function __construct(array $vars = [])
    {
        parent::__construct($vars);
        $this->extension = '.twig';
    }

    protected function ensureTwig(): void
    {
        if (isset($this->twig)) {
            return;
        }
        if (!class_exists('\Twig\Environment')) {
            throw new \RuntimeException('Twig not installed. Run: composer require --dev twig/twig');
        }
        $loader = new \Twig\Loader\FilesystemLoader($this->viewPath);
        $this->twig = new \Twig\Environment($loader, ['cache' => sys_get_temp_dir() . '/twig_cache']);
    }

    public function render(string $view, array $vars = []): string
    {
        $this->ensureTwig();
        $this->twig->setLoader(new \Twig\Loader\FilesystemLoader($this->viewPath));
        $path = $this->resolveView($view);
        // Twig expects template name relative to loader path
        $name = basename($path);
        return $this->twig->render($name, array_merge($this->vars, $vars));
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
