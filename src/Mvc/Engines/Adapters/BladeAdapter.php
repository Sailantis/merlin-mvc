<?php
namespace Merlin\Mvc\Engines\Adapters;

use Merlin\Mvc\ViewEngine;

class BladeAdapter extends ViewEngine
{
    protected $factory;

    public function __construct(array $vars = [])
    {
        parent::__construct($vars);
        $this->extension = '.blade.php';
    }

    protected function ensureBlade(): void
    {
        if (isset($this->factory)) {
            return;
        }
        if (!class_exists('\Illuminate\View\Factory')) {
            throw new \RuntimeException('Illuminate/View not installed. Run: composer require --dev illuminate/view');
        }
        // Minimal, lazy setup using ArrayLoader + Filesystem for views
        $filesystem = new \Illuminate\Filesystem\Filesystem();
        $engineResolver = new \Illuminate\View\Engines\EngineResolver();
        $bladeCompiler = new \Illuminate\View\Compilers\BladeCompiler(new \Illuminate\Filesystem\Filesystem(), sys_get_temp_dir() . '/blade_cache');
        $engineResolver->register('blade', function () use ($bladeCompiler) {
            return new \Illuminate\View\Engines\CompilerEngine($bladeCompiler);
        });
        $finder = new \Illuminate\View\FileViewFinder($filesystem, [$this->viewPath]);
        $this->factory = new \Illuminate\View\Factory($engineResolver, $finder, new \Illuminate\Events\Dispatcher());
    }

    public function render(string $view, array $vars = []): string
    {
        $this->ensureBlade();
        $name = basename($this->resolveView($view), $this->extension);
        return $this->factory->make($name, array_merge($this->vars, $vars))->render();
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
