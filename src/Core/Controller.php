<?php
namespace Azera\Core;

use Azera\AppContext;
use Azera\Db\DatabaseManager;
use Azera\Http\Cookies;
use Azera\Http\Request;
use Azera\Http\Session;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Base class for controllers in Azera.
 */
abstract class Controller
{
	/**
	 * Controller-wide middleware
	 * Example:
	 * protected array $middlewares = [
	 *     AuthMiddleware::class,
	 *     [RoleMiddleware::class, ['admin']],
	 * ];
	 */
	protected array $middlewares = [];

	/**
	 * Action-specific middleware
	 * Example:
	 * protected array $actionMiddlewares = [
	 *     'editAction' => [
	 *         AuthMiddleware::class,
	 *         [RoleMiddleware::class, ['admin']],
	 *     ],
	 * ];
	 */
	protected array $actionMiddlewares = [];

	// --- Middleware getters ---

	/**
	 * Get the middleware for the controller. Usually used by the Dispatcher to build the middleware pipeline for the current request.
	 * @return array
	 */
	public function getMiddlewares(): array
	{
		return $this->middlewares;
	}

	/**
	 * Get the middleware for a specific action. Usually used by the Dispatcher to build the middleware pipeline for the current request.
	 * @param string $action The name of the action (e.g. "editAction")
	 * @return array
	 */
	public function getActionMiddlewares(string $action): array
	{
		return $this->actionMiddlewares[$action] ?? [];
	}

	// --- Helpers ---

	/**
	 * Get the current AppContext instance. Useful for accessing services or route info from the controller.
	 * @return AppContext
	 */
	public function context(): AppContext
	{
		return AppContext::instance();
	}

	/**
	 * Get the current Request object from the context.
	 * @return Request
	 */
	public function request(): Request
	{
		return $this->context()->request();
	}

	/**
	 * Get the ViewEngine from the context for rendering views.
	 * @return ViewEngine
	 */
	public function view(): ViewEngine
	{
		return $this->context()->view();
	}

	/**
	 * Get the Session from the context. May return null if no session is available.
	 * @return Session|null
	 */
	public function session(): ?Session
	{
		return $this->context()->session();
	}

	/**
	 * Get the Cookies service from the context for managing cookies.
	 * @return Cookies
	 */
	public function cookies(): Cookies
	{
		return $this->context()->cookies();
	}

	/**
	 * Get a service from the context by class name.
	 *
	 * @template T of object
	 * @param class-string<T> $id
	 * @return T
	 */
	public function resolve(string $id): object
	{
		return $this->context()->get($id);
	}

	/**
	 * Get the database manager for persistence access.
	 */
	public function db(): DatabaseManager
	{
		return $this->context()->dbManager();
	}

	/**
	 * Get the cache service.
	 */
	public function cache(): CacheInterface
	{
		return $this->context()->cache();
	}

	/**
	 * Get the logger.
	 */
	public function logger(): LoggerInterface
	{
		return $this->context()->logger();
	}

	/**
	 * Get the event dispatcher.
	 */
	public function events(): EventDispatcherInterface
	{
		return $this->context()->events();
	}
}