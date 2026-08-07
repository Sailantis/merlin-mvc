<?php
namespace Azera;

use Azera\Cache\NullCache;
use Azera\Config\Config;
use Azera\Core\Dispatcher;
use Azera\Core\Engines\ClarityEngine;
use Azera\Core\Router;
use Azera\Core\ViewEngine;
use Azera\Db\DatabaseManager;
use Azera\Event\NullEventDispatcher;
use Azera\Http\Cookies;
use Azera\Http\Request as HttpRequest;
use Azera\Http\Session;
use Azera\Log\NullLogger;
use Azera\Queue\QueueInterface;
use Azera\Aop\Advice;
use Azera\Aop\Advised;
use Azera\Aop\InterceptorInterface;
use Azera\Aop\ProxyFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

class AppContext
{
    public function __construct()
    {
        $this->registerDefaultServices();
    }

    protected function registerDefaultServices(): void
    {
        $this->serviceDefinitions = [
            Session::class         => fn() => $this->session(),
            Cookies::class         => fn() => $this->cookies(),
            HttpRequest::class     => fn() => $this->request(),
            ViewEngine::class      => fn() => $this->view(),
            DatabaseManager::class => fn() => $this->dbManager(),
            Router::class          => fn() => $this->router(),
            Dispatcher::class      => fn() => $this->dispatcher(),
            AppContext::class      => fn() => $this,
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

    protected ?Dispatcher $dispatcher = null;

    protected ?ResolvedRoute $route = null;

    protected DatabaseManager $dbManager;

    protected ?LoggerInterface $logger = null;

    protected ?EventDispatcherInterface $events = null;

    protected ?CacheInterface $cache = null;

    protected ?QueueInterface $queue = null;

    protected ?Config $config = null;

    /** @var array<class-string<Advice>, InterceptorInterface> Map of advice class => interceptor */
    protected array $interceptors = [];

    protected ?ProxyFactory $proxyFactory = null;

    /** @var array<string, bool> Cache of whether a class has #[Advised] */
    private array $advisedCache = [];

    // --- Singleton ---

    /** @var AppContext|null Shared singleton instance */
    private static ?AppContext $instance = null;

    /**
     * Get/create shared singleton instance
     */
    public static function instance(): static
    {
        return self::$instance ??= new static();
    }

    /**
     * Set the shared singleton instance (e.g. for testing or multi-context scenarios).
     * @param AppContext $instance
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
        $this->serviceInstances[ViewEngine::class]   = $engine;
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

    public function dispatcher(): Dispatcher
    {
        return $this->dispatcher ??= new Dispatcher();
    }

    // --- Enterprise Subsystems (lazy, opt-in) ---

    /**
     * Get the logger instance. Returns a {@see NullLogger} if no logger
     * has been registered, so calling code can safely log without
     * null-checks. Register a real logger via `set(LoggerInterface::class, ...)`.
     *
     * @return LoggerInterface
     */
    public function logger(): LoggerInterface
    {
        return $this->logger ??= $this->getOrNull(LoggerInterface::class) ?? new NullLogger();
    }

    /**
     * Get the event dispatcher. Returns a {@see NullEventDispatcher} if
     * none has been registered, so `dispatch()` is always safe. Register
     * a real dispatcher via `set(EventDispatcherInterface::class, ...)`.
     *
     * @return EventDispatcherInterface
     */
    public function events(): EventDispatcherInterface
    {
        return $this->events ??= $this->getOrNull(EventDispatcherInterface::class) ?? new NullEventDispatcher();
    }

    /**
     * Get the cache instance. Returns a {@see NullCache} if none has been
     * registered (always reports a miss). Register a real cache via
     * `set(CacheInterface::class, ...)`.
     *
     * @return CacheInterface
     */
    public function cache(): CacheInterface
    {
        return $this->cache ??= $this->getOrNull(CacheInterface::class) ?? new NullCache();
    }

    /**
     * Get the queue instance.
     *
     * Unlike the other subsystems, the queue has no null implementation
     * because silently dropping jobs would be dangerous. If no queue is
     * registered, this throws a LogicException with an install hint.
     * Register a queue via `set(QueueInterface::class, ...)`.
     *
     * @return QueueInterface
     * @throws \LogicException If no queue is registered.
     */
    public function queue(): QueueInterface
    {
        if ($this->queue !== null) {
            return $this->queue;
        }

        $q = $this->getOrNull(QueueInterface::class);
        if ($q === null) {
            throw new \LogicException(
                'No queue registered. Set one via '
                . 'AppContext::set(QueueInterface::class, $queue). '
                . 'For synchronous processing, use Azera\\Queue\\SyncQueue.'
            );
        }
        return $this->queue = $q;
    }

    /**
     * Get the configuration service. Lazily creates a {@see Config}
     * if none has been registered.
     *
     * @return Config
     */
    public function config(): Config
    {
        return $this->config ??= $this->getOrNull(Config::class) ?? new Config();
    }

    /**
     * Create a pipeline for explicit interceptor composition.
     *
     * This is the no-proxy alternative to AOP attributes. It lets you
     * wrap any callable with interceptors without generating proxy
     * classes. The same interceptors that work with the proxy AOP
     * also work here.
     *
     * Example:
     * <code>
     * $result = $ctx->pipeline()
     *     ->through([new RetryInterceptor(3), new LogInterceptor($logger)])
     *     ->call(fn() => $service->chargeCard(100));
     * </code>
     *
     * @param InterceptorInterface[] $interceptors
     * @return \Azera\Aop\Pipeline
     */
    public function pipeline(array $interceptors = []): \Azera\Aop\Pipeline
    {
        return new \Azera\Aop\Pipeline($interceptors);
    }

    /**
     * Register an interceptor for a specific advice type.
     *
     * Once at least one interceptor is registered, the DI container
     * will proxy classes marked with {@see Advised} that have methods
     * carrying the corresponding advice attribute.
     *
     * @param class-string<Advice>   $adviceClass The advice attribute class.
     * @param InterceptorInterface    $interceptor The interceptor to handle it.
     * @return void
     */
    public function registerInterceptor(string $adviceClass, InterceptorInterface $interceptor): void
    {
        $this->interceptors[$adviceClass] = $interceptor;
    }

    /**
     * Get the ProxyFactory, lazily created and configured with
     * all registered interceptors.
     */
    protected function proxyFactory(): ProxyFactory
    {
        if ($this->proxyFactory === null) {
            $this->proxyFactory = new ProxyFactory();
            // Default cache dir: sys_get_temp_dir()/azera_aop (OPcache-friendly).
            // Set to null via setAopCacheDir(null) to use eval (development).
            $this->proxyFactory->setCacheDir(
                $this->aopCacheDir
                ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'azera_aop'
            );
            foreach ($this->interceptors as $adviceClass => $interceptor) {
                $this->proxyFactory->register($adviceClass, $interceptor);
            }
            ProxyFactory::setCurrent($this->proxyFactory);
        }
        return $this->proxyFactory;
    }

    /**
     * Set the AOP proxy cache directory.
     *
     * Pass a path for file-based proxy generation (OPcache-cached, production).
     * Pass null to use eval() (development, no cache files).
     *
     * @param string|null $dir
     * @return void
     */
    public function setAopCacheDir(?string $dir): void
    {
        if ($this->proxyFactory !== null) {
            $this->proxyFactory->setCacheDir($dir);
        }
        $this->aopCacheDir = $dir;
    }

    /** @var string|null AOP cache directory (stored before proxyFactory is created). */
    private ?string $aopCacheDir = null;

    /**
     * Check if a class is marked with #[Advised] and has at least one
     * method with a registered advice attribute. Cached per class.
     */
    private function hasAdvisedMethods(\ReflectionClass $ref): bool
    {
        $className = $ref->getName();

        if (isset($this->advisedCache[$className])) {
            return $this->advisedCache[$className];
        }

        // Fast check: class (or any parent) must have #[Advised] attribute.
        // Class-level attributes are NOT inherited by PHP, so we walk
        // up the parent chain ourselves.
        $hasAdvised = false;
        $class      = $ref;
        do {
            if ($class->getAttributes(Advised::class) !== []) {
                $hasAdvised = true;
                break;
            }
        } while ($class = $class->getParentClass());

        if (!$hasAdvised) {
            return $this->advisedCache[$className] = false;
        }

        // Check if any method has a registered advice attribute.
        // Method-level attributes ARE inherited in PHP — getAttributes()
        // on an inherited method returns the declaring class's attributes.
        foreach ($ref->getMethods() as $method) {
            foreach ($method->getAttributes() as $attr) {
                $attrClass = $attr->getName();
                if (isset($this->interceptors[$attrClass])) {
                    return $this->advisedCache[$className] = true;
                }
            }
        }

        return $this->advisedCache[$className] = false;
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
        $this->session                            = $session;
        $this->serviceDefinitions[Session::class] = $session;
        $this->serviceInstances[Session::class]   = $session;
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
     * @template T of object
     * @param class-string<T> $id The identifier of the service to retrieve.
     * @return T The service instance associated with the given identifier.
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
            $this->serviceInstances[$id]   = $service;
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
     * @template T of object
     * @param class-string<T> $id The identifier of the service to retrieve.
     * @return T|null The service instance associated with the given identifier, or null if not found.
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
            $this->serviceInstances[$id]   = $service;
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
     * @template T of object
     * @param class-string<T> $id The identifier of the service to retrieve.
     * @return T|null The service instance associated with the given identifier, or null if not found.
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
            $this->serviceInstances[$id]   = $service;
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
        if ($service !== null && !$service instanceof $id) {
            return; // The service does not match the expected type, skip syncing
        }
        switch ($id) {
            case HttpRequest::class:
                $this->request = $service;
                break;
            case ViewEngine::class:
                $this->view = $service;
                break;
            case Session::class:
                $this->session = $service;
                break;
            case Cookies::class:
                $this->cookies = $service;
                break;
            case Router::class:
                $this->router = $service;
                break;
            case Dispatcher::class:
                $this->dispatcher = $service;
                break;
            case DatabaseManager::class:
                $this->dbManager = $service;
                break;
            case LoggerInterface::class:
                $this->logger = $service;
                break;
            case EventDispatcherInterface::class:
                $this->events = $service;
                break;
            case CacheInterface::class:
                $this->cache = $service;
                break;
            case QueueInterface::class:
                $this->queue = $service;
                break;
            case Config::class:
                $this->config = $service;
                break;
        }
    }

    protected function build(string $class): object
    {
        $ref = new \ReflectionClass($class);

        // AOP fast path: if no interceptors registered, instantiate directly.
        if ($this->interceptors === []) {
            return $this->instantiate($ref, $class);
        }

        // AOP fast path: if class is not #[Advised] with matching methods,
        // instantiate directly — zero proxy overhead.
        if (!$this->hasAdvisedMethods($ref)) {
            return $this->instantiate($ref, $class);
        }

        // Build the proxy class and instantiate it directly.
        // The proxy extends the target class, so constructor DI works
        // the same way — the proxy IS the instance.
        $proxyClass = $this->proxyFactory()->buildProxyClass($ref);

        if ($proxyClass === null) {
            return $this->instantiate($ref, $class);
        }

        return $this->instantiate(new \ReflectionClass($proxyClass), $proxyClass);
    }

    /**
     * Instantiate a class, resolving constructor dependencies via DI.
     */
    private function instantiate(\ReflectionClass $ref, string $class): object
    {
        if (!$ref->getConstructor()) {
            return new $class();
        }
        return $this->instantiateWithDeps($ref, $class);
    }

    /**
     * Instantiate a class with constructor dependencies resolved via DI.
     */
    private function instantiateWithDeps(\ReflectionClass $ref, string $class): object
    {

        $args = [];

        foreach ($ref->getConstructor()->getParameters() as $param) {

            $typeObj = $param->getType();
            $types   = [];

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
    ) {}
}