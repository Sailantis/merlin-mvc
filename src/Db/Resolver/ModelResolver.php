<?php

declare(strict_types=1);

namespace Azera\Db\Resolver;

use Azera\Orm\Model;
use Azera\Lifecycle\RequestScoped;

/**
 * Resolves logical names as fully-qualified model class names.
 *
 * If the name is an existing class that extends {@see Model}, the resolver
 * instantiates it (cached per class) and returns the model's `source()`,
 * `schema()`, read/write roles, and `idFields()`. This enables `FETCH_CLASS`
 * hydration via the returned `modelClass`.
 *
 * If the name is not a valid model class, a {@see ResolveException} is thrown.
 * This preserves typo detection — `table('Userr')` throws rather than silently
 * falling back to a literal table.
 *
 * The model instance cache is an instance property. When the `ModelResolver`
 * is registered as a singleton in `AppContext`, the cache persists across
 * RoadRunner requests. This is safe because `Model` instances are stateless
 * with respect to connections — `readConnection()`/`writeConnection()` resolve
 * fresh via `AppContext::dbManager()` on every call. Implementing
 * {@see RequestScoped} ensures the cache is cleared after every request in a
 * persistent worker, so no state leaks from one request into the next.
 */
class ModelResolver implements TableResolver, RequestScoped
{
    /** @var array<string, Model> Cached model instances, keyed by class name */
    private array $cache = [];

    public function resolve(string $name): array
    {
        $model = $this->cache[$name] ??= $this->instantiate($name);

        return [
            'source' => $model->source(),
            'schema' => $model->schema(),
            'read' => $model->readRole(),
            'write' => $model->writeRole(),
            'modelClass' => $name,
            'idFields' => $model->idFields(),
        ];
    }

    /**
     * Clear the model instance cache (useful for testing).
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Request-scoped hook: clear the model instance cache after each request
     * in a persistent worker so no state leaks into the next request.
     */
    public function resetState(): void
    {
        $this->clearCache();
    }

    private function instantiate(string $name): Model
    {
        if (!class_exists($name)) {
            throw new ResolveException("Unknown model '{$name}' (class does not exist)");
        }

        if (!is_subclass_of($name, Model::class)) {
            throw new ResolveException("'{$name}' is not a valid model (does not extend " . Model::class . ")");
        }

        return new $name();
    }
}