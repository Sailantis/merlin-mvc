<?php

namespace Merlin\Mvc;

use Merlin\AppContext;
use Merlin\ResolvedRoute;
use Merlin\Http\Response;
use InvalidArgumentException;
use Merlin\Mvc\Exceptions\ActionNotFoundException;
use Merlin\Mvc\Exceptions\InvalidControllerException;
use Merlin\Mvc\Exceptions\ControllerNotFoundException;

class Dispatcher
{
    /**
     * Method names that end with 'Action' but are lifecycle hooks, not
     * dispatchable actions. Attempting to dispatch one via a dynamic route
     * will result in an {@see ActionNotFoundException}.
     */
    protected const RESERVED_ACTIONS = [
        'beforeAction' => true,
        'afterAction' => true
    ];

    protected AppContext $context;

    /**
     * Create a new Dispatcher and bind it to the current {@see AppContext} singleton.
     */
    public function __construct()
    {
        $this->context = AppContext::instance();
        $this->baseNamespace = '\\App\\Controllers';
    }

    protected array $globalMiddleware = [];

    /**
     * Register a middleware that runs on every dispatched request.
     *
     * Global middleware is prepended to the pipeline before any group,
     * controller, or action middleware.
     *
     * @param MiddlewareInterface $mw Middleware instance to add.
     */
    public function addMiddleware(MiddlewareInterface $mw): void
    {
        $this->globalMiddleware[] = $mw;
    }

    protected array $middlewareGroups = [];

    /**
     * Define a named middleware group that can be referenced from route definitions.
     *
     * Groups are applied after global middleware and before controller/action
     * middleware. If several middleware groups are active for a route, they are
     * applied in the order they are listed on the route.
     *
     * @param string $name       Unique group name (e.g. "auth", "admin").
     * @param array  $middleware Array of middleware definitions accepted by the pipeline normalizer.
     */
    public function defineMiddlewareGroup(string $name, array $middleware): void
    {
        $this->middlewareGroups[$name] = $middleware;
    }

    protected string $baseNamespace;
    protected string $defaultController = "IndexController";
    protected string $defaultAction = "indexAction";

    /**
     * Get the base namespace for controllers.
     *
     * @return string The base namespace for controllers.
     */
    public function getBaseNamespace(): string
    {
        return $this->baseNamespace;
    }

    /**
     * Set the base namespace for controllers. This namespace will be prefixed to all controller class names when dispatching.
     *
     * @param string $baseNamespace The base namespace for controllers (e.g. "App\\Controllers")
     * @return $this
     */
    public function setBaseNamespace(string $baseNamespace): static
    {
        $this->baseNamespace = rtrim($baseNamespace, '\\');
        return $this;
    }

    /**
     * Get the default controller name used when a route doesn't provide one.
     *
     * @return string Default controller class name (without namespace)
     */
    public function getDefaultController(): string
    {
        return $this->defaultController;
    }

    /**
     * Set the default controller name.
     *
     * @param string $defaultController Controller class name to use as default
     * @throws InvalidArgumentException If given name is empty
     */
    public function setDefaultController(string $defaultController): static
    {
        if (empty($defaultController)) {
            throw new InvalidArgumentException("Default controller cannot be empty");
        }
        $this->defaultController = $defaultController;
        return $this;
    }

    /**
     * Get the default action name used when a route doesn't provide one.
     *
     * @return string Default action method name
     */
    public function getDefaultAction(): string
    {
        return $this->defaultAction;
    }

    /**
     * Set the default action name.
     *
     * @param string $defaultAction Action method name to use as default
     * @throws InvalidArgumentException If given name is empty
     */
    public function setDefaultAction(string $defaultAction): static
    {
        if (empty($defaultAction)) {
            throw new InvalidArgumentException("Default action cannot be empty");
        }
        $this->defaultAction = $defaultAction;
        return $this;
    }

    /**
     * Dispatch a request to the appropriate controller and action based on the provided routing information. This method will determine the controller class and action method to invoke, build the middleware pipeline, and execute the controller action, returning the resulting Response.
     * @param array $routeInfo
     * @throws ControllerNotFoundException
     * @throws InvalidControllerException
     * @throws ActionNotFoundException
     * @return Response
     */
    public function dispatch(array $routeInfo): Response
    {
        $groups = $routeInfo['groups'] ?? [];
        $vars = $routeInfo['vars'] ?? [];
        $override = $routeInfo['override'] ?? [];

        if (!empty($override['namespace'])) {
            $namespace = rtrim((string) $override['namespace'], '\\');
            if (empty($namespace)) {
                $namespace = $this->baseNamespace;
            } elseif ($namespace[0] !== '\\' && !empty($this->baseNamespace)) {
                $namespace = $this->baseNamespace . '\\' . $namespace;
            }
        } else {
            $namespace = $this->baseNamespace;
            if (!empty($vars['namespace'])) {
                if ($namespace !== '') {
                    $namespace .= '\\';
                }
                $namespace .= $this->camelize((string) $vars['namespace']);
            }
        }

        if (!empty($override['controller'])) {
            $controllerName = (string) $override['controller'];
        } elseif (!empty($vars['controller'])) {
            $controllerName = $this->camelize((string) $vars['controller']) . 'Controller';
        } else {
            $controllerName = $this->defaultController;
        }

        if (!empty($override['action'])) {
            $actionName = (string) $override['action'];
        } elseif (!empty($vars['action'])) {
            $actionName = $this->camelize((string) $vars['action'], false) . 'Action';
        } else {
            $actionName = $this->defaultAction;
        }

        if (isset(static::RESERVED_ACTIONS[$actionName])) {
            throw new ActionNotFoundException(
                "Action '{$actionName}' is a lifecycle hook and cannot be dispatched directly."
            );
        }

        $controllerClass = $namespace !== ''
            ? $namespace . '\\' . $controllerName
            : $controllerName;

        /**
         * @var Controller $controller
         */
        $controller = $this->context->get($controllerClass);

        if (!$controller instanceof Controller) {
            throw new InvalidControllerException("{$controllerClass} is not a Controller");
        }

        $params = $this->resolveActionArguments(
            $controller,
            $actionName,
            $vars,
            $this->context
        );

        $this->context->setRoute(new ResolvedRoute(
            $namespace,
            $controllerClass,
            $actionName,
            $params,
            $vars,
            $groups,
            $override
        ));

        $pipeline = $this->buildPipeline(
            $controller,
            $actionName,
            $params,
            $groups,
            $this->context
        );

        $response = $pipeline();

        return $response ?? Response::status(204);
    }

    protected function resolveActionArguments(
        object $controller,
        string $action,
        array $routeParams,
        AppContext $context
    ): array {

        try {
            $ref = new \ReflectionMethod($controller, $action);
        } catch (\ReflectionException $e) {
            throw new ActionNotFoundException("Action " . \get_class($controller) . "->{$action} not found", previous: $e);
        }
        $args = [];

        foreach ($ref->getParameters() as $index => $param) {

            $name = $param->getName();
            $typeObj = $param->getType();

            // Extract type names (for union types)
            $types = [];

            if ($typeObj instanceof \ReflectionNamedType) {
                $types[] = $typeObj->getName();
            } elseif ($typeObj instanceof \ReflectionUnionType) {
                foreach ($typeObj->getTypes() as $t) {
                    if ($t instanceof \ReflectionNamedType) {
                        $types[] = $t->getName();
                    }
                }
            } elseif ($typeObj !== null) {
                throw new \RuntimeException(
                    "Unsupported parameter type for \${$name} for " . \get_class($controller) . "->{$action}()"
                );
            }

            // Route parameters (by name)
            if (isset($routeParams[$name])) {
                $value = $routeParams[$name];

                // Variadic wildcard support 
                if ($param->isVariadic()) {
                    $array = (array) $value;
                    foreach ($array as $value) {
                        $args[] = $value;
                    }
                    continue;
                }

                // Type conversion for simple built-in types
                if (!empty($types)) {
                    $value = $this->castValueToType($value, $types);
                }

                $args[] = $value;
                continue;
            }

            // DI auto-resolve by type hint
            if (!empty($types)) {
                foreach ($types as $t) {

                    // If type is registered in AppContext, use it
                    if ($context->has($t)) {
                        $service = $context->getOrNull($t);
                        if ($service !== null) {
                            $args[] = $service;
                            continue 2; // next parameter
                        }

                        continue;
                    }

                    // If type is a class → Auto-Wiring
                    if (class_exists($t)) {
                        $args[] = $context->get($t);
                        continue 2;
                    }

                }
            }

            // Variadic parameter → empty array
            if ($param->isVariadic()) {
                continue;
            }

            // Default value from signature
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // Nullable parameter → null
            if ($param->allowsNull()) {
                $args[] = null;
                continue;
            }

            // Not resolved → Exception
            throw new \RuntimeException("Cannot resolve parameter \${$name} for action {$action}()");
        }

        return $args;
    }

    protected function castValueToType(mixed $value, array $types): mixed
    {
        foreach ($types as $t) {

            switch ($t) {

                case 'int':
                    if (\ctype_digit((string) $value)) {
                        return (int) $value;
                    }
                    break;

                case 'float':
                    if (\is_numeric((string) $value)) {
                        return (float) $value;
                    }
                    break;

                case 'bool':
                    // true/false/1/0/yes/no/on/off
                    $filtered = \filter_var((string) $value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                    if ($filtered !== null) {
                        return $filtered;
                    }
                    break;

                case 'string':
                    return (string) $value;

                case 'array':
                    if (\is_array($value)) {
                        return $value;
                    }
                    break;

                case 'mixed':
                    return $value;
            }
        }

        return $value;
    }


    protected function camelize(string $string, bool $beginUpper = true): string
    {
        $string = str_replace(['-', '_', '.'], ' ', $string);

        $result = '';
        foreach (explode(' ', $string) as $part) {
            if ($part === '') {
                continue;
            }

            if (!$beginUpper && $result === '') {
                $result .= strtolower($part);
                continue;
            }

            $result .= strtoupper($part[0]) . strtolower(substr($part, 1));
        }

        return $result;
    }

    /**
     * Build the middleware pipeline for the given controller action. The pipeline will include global middleware, controller-specific middleware, and action-specific middleware, in that order. Each middleware will be normalized to an instance of MiddlewareInterface, allowing for flexible definitions (class name, instance, closure, or array with arguments). The final callable returned by this method will execute the entire middleware stack and ultimately invoke the controller action.
     *
     * @param Controller $controller The controller instance to invoke
     * @param string $action The action method name to invoke on the controller
     * @param array $params Parameters to pass to the controller action
     * @param ?array $groups Middleware groups to apply to the pipeline
     * @param AppContext $context The application context to pass to middleware and controller
     * @return callable A callable that executes the middleware pipeline and invokes the controller action when called
     */
    protected function buildPipeline(Controller $controller, string $action, array $params, ?array $groups, AppContext $context): callable
    {
        $middleware = [];

        // 1) Global Middleware
        if (!empty($this->globalMiddleware)) {
            foreach ($this->globalMiddleware as $mw) {
                $middleware[] = $this->normalizeMiddleware($mw);
            }
        }

        // 2) Middleware from Route Groups
        if (!empty($groups)) {
            foreach ($groups as $groupName) {
                if (!empty($this->middlewareGroups[$groupName])) {
                    foreach ($this->middlewareGroups[$groupName] as $mw) {
                        $middleware[] = $this->normalizeMiddleware($mw);
                    }
                }
            }
        }

        // 2) Controller-based Middleware
        $controllerMiddleware = $controller->getMiddleware();
        if (!empty($controllerMiddleware)) {
            foreach ($controllerMiddleware as $mw) {
                $middleware[] = $this->normalizeMiddleware($mw);
            }
        }

        // 3) Action-based Middleware
        $actionMiddleware = $controller->getActionMiddleware($action);
        if (!empty($actionMiddleware)) {
            foreach ($actionMiddleware as $mw) {
                $middleware[] = $this->normalizeMiddleware($mw);
            }
        }

        // Core-Handler
        $core = fn() => $this->invokeController(
            $controller,
            $action,
            $params
        );

        // If no middleware, return direct controller invocation
        if (empty($middleware)) {
            return $core;
        }

        // Run the pipeline
        $next = $core;

        foreach (array_reverse($middleware) as $mw) {
            $current = $mw;
            $next = fn() => $current->process($context, $next);
        }

        return $next;
    }

    private function normalizeMiddleware($mw): MiddlewareInterface
    {
        if ($mw instanceof MiddlewareInterface) {
            return $mw;
        }

        if ($mw instanceof \Closure) {
            return new class ($mw) implements MiddlewareInterface {
                public function __construct(private \Closure $fn)
                {}
                public function process($ctx, $next): ?Response
                {
                    return ($this->fn)($ctx, $next);
                }
            };
        }

        if (\is_array($mw)) {
            if (!isset($mw[0])) {
                throw new InvalidArgumentException("Middleware array must have a class name at index 0");
            }
            if (!isset($mw[1])) {
                $mw[1] = [];
            }
            return new $mw[0](...$mw[1]);
        }

        return new $mw();
    }

    protected function invokeController(Controller $controller, string $action, array $params): Response
    {
        $before = $controller->beforeAction($action, $params);
        if ($before instanceof Response) {
            return $before;
        }

        try {
            $result = $controller->$action(...$params);
        } finally {
            $after = $controller->afterAction($action, $params);
            if ($after instanceof Response) {
                $result = $after;
            }
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (\is_array($result))
            return Response::json($result);
        elseif ($result instanceof \JsonSerializable)
            return Response::json($result->jsonSerialize());
        elseif (\is_string($result))
            return Response::html($result);
        elseif (\is_int($result))
            return Response::status($result);
        elseif ($result === null)
            return Response::status(204); // No Content
        else
            throw new \UnexpectedValueException("Unsupported controller action return type: " . \get_debug_type($result));
    }


}
