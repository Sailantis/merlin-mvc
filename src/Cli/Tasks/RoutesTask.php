<?php

namespace Azera\Cli\Tasks;

use Azera\AppContext;
use Azera\Cli\Task;

/**
 * List all registered routes.
 *
 * Usage:
 *   routes:list
 *
 * Displays a table of all routes registered via the Router, showing
 * HTTP method, path pattern, handler (controller::action), route name
 * (if assigned), and middleware groups.
 *
 * Routes are registered during application bootstrap. If your Bootstrap
 * class implements a `registerRoutes(Router $router)` method, it will
 * be called automatically by the `azera` binary before any task runs.
 */
class RoutesTask extends Task
{
    /**
     * List all registered routes.
     */
    public function listAction(): void
    {
        $ctx    = AppContext::instance();
        $router = $ctx->router();
        $routes = $router->allRoutes();

        if (empty($routes)) {
            $this->muted('No routes registered.');
            $this->line('');
            $this->muted('Implement registerRoutes(Router $router) on your Bootstrap class');
            $this->muted('to enable route registration for CLI tasks.');
            return;
        }

        // Resolve controller namespace from the dispatcher for display
        $namespace = '';
        try {
            $namespace = $ctx->dispatcher()->getBaseNamespace();
        } catch (\Throwable) {}

        $headers = ['Method', 'Path', 'Handler', 'Name', 'Middleware'];
        $rows    = [];

        foreach ($routes as $route) {
            $method  = $route['method'];
            $pattern = $route['pattern'];
            $handler = $this->formatHandler($route['handler'], $namespace);
            $name    = $route['name'] ?? '';
            $groups  = !empty($route['groups']) ? implode(', ', $route['groups']) : '';

            $rows[] = [
                $this->style($method, 'bmagenta'),
                $this->style($pattern, 'cyan'),
                $handler,
                $name !== '' ? $this->style($name, 'byellow') : '',
                $this->style($groups, 'gray'),
            ];
        }

        usort(
            $rows,
            function ($a, $b) {
                $diff = strnatcmp($a[1], $b[1]);
                if ($diff === 0) {
                    $diff = strcmp($a[0], $b[0]);
                }
                return $diff;
            }
        );

        $this->console->printTable($headers, $rows);

        $this->muted("\n" . count($routes) . ' ' . (count($routes) > 1 ? 'routes' : 'route') . '.');
    }

    // -----------------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------------

    /**
     * Format a route handler for display.
     */
    private function formatHandler(string|array|null $handler, string $namespace): string
    {
        if ($handler === null) {
            return $this->style('(auto)', 'gray');
        }

        if (is_array($handler)) {
            $dispatcher = AppContext::instance()->dispatcher();
            $ns         = $handler['namespace'] ?? '';
            $ctrl       = $handler['controller'] ?? $dispatcher->getDefaultController();
            $action     = $handler['action'] ?? $dispatcher->getDefaultAction();
            $fullCtrl   = $this->joinNamespace($namespace, $ns, $ctrl);
            return $fullCtrl . ($action !== '' ? '::' . $action : '');
        }

        // String handler: "Controller::action" or "::action" or "Controller"
        if (is_string($handler)) {
            if (str_starts_with($handler, '::')) {
                return $this->style('(controller)', 'gray') . $handler;
            }
            if (!str_contains($handler, '\\') && $namespace !== '') {
                $handler = ltrim($namespace, '\\') . '\\' . $handler;
            }
            return $handler;
        }

        return '';
    }

    private function joinNamespace(string $baseNs, string $groupNs, string $controller): string
    {
        $parts = [];
        if ($baseNs !== '') {
            $parts[] = trim($baseNs, '\\');
        }
        if ($groupNs !== '') {
            $parts[] = trim($groupNs, '\\');
        }
        $prefix = implode('\\', $parts);
        if ($controller === '') {
            return $prefix;
        }
        if (str_contains($controller, '\\')) {
            return ltrim($controller, '\\');
        }
        return $prefix !== '' ? $prefix . '\\' . $controller : $controller;
    }

}