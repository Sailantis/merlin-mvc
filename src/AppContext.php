<?php
namespace Merlin;

use RuntimeException;
use Merlin\Db\DatabaseManager;
use Merlin\Http\Cookies;
use Merlin\Http\Request as HttpRequest;
use Merlin\Http\Session;
use Merlin\Mvc\Engines\ClarityEngine;
use Merlin\Mvc\Router;
use Merlin\Mvc\ViewEngine;

class AppContext
{
    public function __construct()
    {
        $this->registerDefaultServices();
    }

    protected function registerDefaultServices(): void
    {
        $this->serviceDefinitions = [
            Session::class => fn() => $this->session(),
            Cookies::class => fn() => $this->cookies(),
            HttpRequest::class => fn() => $this->request(),
            ViewEngine::class => fn() => $this->view(),
            DatabaseManager::class => fn() => $this->dbManager(),
            AppContext::class => fn() => $this,
        ];

        $this->serviceInstances = [];
    }

    protected array $serviceDefinitions = [];

    protected array $serviceInstances = [];

    protected ?HttpRequest $request = null;

    protected ?ViewEngine $view = null;

    protected ?Session $session = null;

    protected ?Cookies $cookies = null;

    protected ?Router $router = null;

    protected ?ResolvedRoute $route = null;

    protected DatabaseManager $dbManager;

    // --- Singleton ---

    /** @var AppContext|null Shared singleton instance */
    private static ?AppContext $instance = null;

    /**
     * Get/create shared singleton instance
     */
    public static function instance(): static
    {
        // Thread-safe PHP 8.0+
        return self::$instance ??= new static();
    }

    /**
     * Set shared instance (affects ALL subclasses)
     */
    public static function setInstance(AppContext $instance): void
    {
        self::$instance = $instance;
    }

    // --- Lazy Services ---

    /**
     * Get the HttpRequest instance. If it doesn't exist, it will be created.
     *
     * @return HttpRequest The HttpRequest instance.
     */
    public function request(): HttpRequest
    {
        return $this->request ??= new HttpRequest();
    }

    /**
     * Get the active view engine instance. Defaults to ClarityEngine.
     *
     * @return ViewEngine The active view engine instance.
     */
    public function view(): ViewEngine
    {
        return $this->view ??= new ClarityEngine();
    }

    /**
     * Replace the active view engine (e.g. swap in ClarityEngine at bootstrap).
     *
     * @param ViewEngine $engine The engine to use from this point on.
     * @return static
     */
    public function setView(ViewEngine $engine): static
    {
        $this->view = $engine;
        $this->serviceDefinitions[ViewEngine::class] = $engine;
        $this->serviceInstances[ViewEngine::class] = $engine;
        return $this;
    }

    /**
     * Get the Cookies instance. If it doesn't exist, it will be created.
     *
     * @return Cookies The Cookies instance.
     */
    public function cookies(): Cookies
    {
        return $this->cookies ??= new Cookies();
    }


    /**
     * Get the DatabaseManager instance. If it doesn't exist, it will be created.
     *
     * @return DatabaseManager The DatabaseManager instance.
     */
    public function dbManager(): DatabaseManager
    {
        return $this->dbManager ??= new DatabaseManager();
    }

    /**
     * Get the Router instance. If it doesn't exist, it will be created.
     *
     * @return Router The Router instance.
     */
    public function router(): Router
    {
        return $this->router ??= new Router();
    }

    // --- Critical Services ---

    /**
     * Get the Session instance.
     */
    public function session(): ?Session
    {
        return $this->session;
    }

    /**
     * Set the Session instance.
     *
     * @param Session $session The Session instance to set in the context.
     */
    public function setSession(Session $session): void
    {
        $this->session = $session;
        $this->serviceDefinitions[Session::class] = $session;
        $this->serviceInstances[Session::class] = $session;
    }

    /**
     * Get the current resolved route information.
     */
    public function route(): ?ResolvedRoute
    {
        return $this->route;
    }

    /**
     * Set the current resolved route information.
     *
     * @param ResolvedRoute $route The resolved route to set in the context.
     */
    public function setRoute(ResolvedRoute $route): void
    {
        $this->route = $route;
    }

    // --- Service Container ---

    /**
     * Register a service instance or lazy factory in the context.
     *
     * Registered callables are treated as zero-argument factories. They are invoked on
     * first resolution and their returned object is cached for subsequent lookups.
     *
     * @param string          $id      The identifier for the service (usually the class name).
     * @param callable|object|null $service Optional service instance or zero-argument factory to register.
     */
    public function set(string $id, callable|object|null $service = null): void
    {
        $service ??= $id;
        $this->serviceDefinitions[$id] = $service;
        unset($this->serviceInstances[$id]);

        if (is_object($service) && !is_callable($service)) {
            $this->syncKnownServiceProperty($id, $service);
            $this->serviceInstances[$id] = $service;
        }
    }

    /**
     * Check if a service is registered in the context.
     *
     * @param string $id The identifier of the service to check.
     * @return bool True if the service is registered, false otherwise.
     */
    public function has(string $id): bool
    {
        return isset($this->serviceDefinitions[$id]);
    }

    /**
     * Get a service instance from the context.
     *
     * If the service is registered as a callable, it will be invoked lazily once and the
     * returned object will be cached. If the service is not registered but the identifier
     * is a class name, it will attempt to auto-wire and instantiate it.
     *
     * @param string $id The identifier of the service to retrieve.
     * @return object The service instance associated with the given identifier.
     * @throws RuntimeException If the service is not found and cannot be auto-wired.
     */
    public function get(string $id): object
    {
        $service = $this->resolveRegisteredService($id, allowNull: false);
        if ($service !== null) {
            return $service;
        }

        if (class_exists($id)) {
            $service = $this->build($id);
            $this->serviceDefinitions[$id] = $service;
            $this->serviceInstances[$id] = $service;
            $this->syncKnownServiceProperty($id, $service);
            return $service;
        }

        throw new RuntimeException("Service not found: $id");
    }

    /**
     * Try to get a service instance from the context.
     *
     * If the service is registered as a callable, it will be invoked lazily once and the
     * returned object will be cached. If the service is not registered but the identifier
     * is a class name, it will attempt to auto-wire and instantiate it. Returns null if
     * the service is not found, or if a registered factory currently resolves to null.
     *
     * @param string $id The identifier of the service to retrieve.
     * @return object|null The service instance associated with the given identifier, or null if not found.
     */
    public function tryGet(string $id): ?object
    {
        $service = $this->resolveRegisteredService($id, allowNull: true);
        if ($service !== null || $this->has($id)) {
            return $service;
        }

        if (class_exists($id)) {
            $service = $this->build($id);
            $this->serviceDefinitions[$id] = $service;
            $this->serviceInstances[$id] = $service;
            $this->syncKnownServiceProperty($id, $service);
            return $service;
        }

        return null;
    }

    /**
     * Get a registered service instance if it exists, or null if it does not.
     *
     * Registered factories are resolved lazily. This method does not attempt to auto-wire
     * or instantiate classes that have not been registered explicitly.
     *
     * @param string $id The identifier of the service to retrieve.
     * @return object|null The service instance associated with the given identifier, or null if not found.
     */
    public function getOrNull(string $id): ?object
    {
        return $this->resolveRegisteredService($id, allowNull: true);
    }

    protected function resolveRegisteredService(string $id, bool $allowNull): ?object
    {
        if (isset($this->serviceInstances[$id])) {
            return $this->serviceInstances[$id];
        }

        $definition = $this->serviceDefinitions[$id] ?? null;

        if ($definition === null) {
            return null;
        }

        if (is_string($definition) && class_exists($definition)) {
            $service = $this->build($definition);
            $this->serviceDefinitions[$id] = $service;
            $this->serviceInstances[$id] = $service;
            $this->syncKnownServiceProperty($id, $service);
            return $service;
        }

        if (!is_callable($definition)) {
            $this->syncKnownServiceProperty($id, $definition);
            return $this->serviceInstances[$id] = $definition;
        }

        $service = $definition();

        if ($service === null) {
            if ($allowNull) {
                return null;
            }

            throw new RuntimeException("Service factory for $id did not return an object");
        }

        if (!is_object($service)) {
            throw new RuntimeException("Service factory for $id did not return an object");
        }

        $this->syncKnownServiceProperty($id, $service);

        return $this->serviceInstances[$id] = $service;
    }

    protected function syncKnownServiceProperty(string $id, object $service): void
    {
        if ($id === HttpRequest::class && $service instanceof HttpRequest) {
            $this->request = $service;
            return;
        }

        if ($id === ViewEngine::class && $service instanceof ViewEngine) {
            $this->view = $service;
            return;
        }

        if ($id === Session::class && $service instanceof Session) {
            $this->session = $service;
            return;
        }

        if ($id === Cookies::class && $service instanceof Cookies) {
            $this->cookies = $service;
            return;
        }

        if ($id === Router::class && $service instanceof Router) {
            $this->router = $service;
            return;
        }

        if ($id === DatabaseManager::class && $service instanceof DatabaseManager) {
            $this->dbManager = $service;
        }
    }

    protected function build(string $class): object
    {
        $ref = new \ReflectionClass($class);

        // No constructor -> simple instantiation
        if (!$ref->getConstructor()) {
            return new $class();
        }

        $args = [];

        foreach ($ref->getConstructor()->getParameters() as $param) {

            $typeObj = $param->getType();
            $types = [];

            // Extract all possible types (Named, Union, Intersection)
            if ($typeObj instanceof \ReflectionNamedType) {
                $types[] = $typeObj->getName();
            } elseif ($typeObj instanceof \ReflectionUnionType) {
                foreach ($typeObj->getTypes() as $t) {
                    if ($t instanceof \ReflectionNamedType) {
                        $types[] = $t->getName();
                    }
                }
            } else {
                throw new RuntimeException(
                    "Unsupported parameter type for \${$param->getName()} in $class constructor"
                );
            }

            // Try to resolve via DI (AppContext)
            foreach ($types as $t) {

                // If service is registered
                if ($this->has($t)) {
                    $service = $this->getOrNull($t);
                    if ($service !== null) {
                        $args[] = $service;
                        continue 2; // next parameter
                    }

                    continue;
                }

                // If class exists -> auto-wire
                if (class_exists($t)) {
                    $args[] = $this->get($t);
                    continue 2;
                }

                // Built-in types (int, string, etc.) are not supported here
            }

            // Default value
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // Nullable
            if ($param->allowsNull()) {
                $args[] = null;
                continue;
            }

            throw new Exception(
                "Cannot resolve constructor parameter \${$param->getName()} for $class"
            );
        }

        return new $class(...$args);
    }
}

/**
 * ResolvedRoute represents the fully resolved route and execution context
 * used by the dispatcher to invoke the matched controller and action.
 */
class ResolvedRoute
{
    /**
     * Create a new ResolvedRoute instance with the given parameters.
     *
     * @param string|null $namespace Effective namespace for the controller, after applying route group namespaces. Null if no namespace is used.
     * @param string      $controller Resolved controller class name.
     * @param string      $action Resolved action method name.
     * @param array       $params Resolved action method parameters.
     * @param array       $vars Associative array of route variables extracted from the URL (e.g. ['id' => '123']).
     * @param array       $groups List of middleware groups to apply for this route.
     * @param array       $override Associative array of route overrides (e.g. ['controller' => 'OtherController', 'action' => 'otherAction']).
     */
    public function __construct(
        public readonly ?string $namespace,
        public readonly string $controller,
        public readonly string $action,
        public readonly array $params,
        public readonly array $vars,
        public readonly array $groups,
        public readonly array $override
    ) {
    }
}
