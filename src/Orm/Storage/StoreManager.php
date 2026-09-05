<?php

namespace Azera\Orm\Storage;

use Azera\Lifecycle\RequestScoped;

/**
 * Service registry: (store type, role) -> Store instance. The type dimension
 * ('sql' | 'mongo') splits the maps — a #[Document] class must never resolve
 * the SQL PdoStore (its SQL-shaped fallback would silently write a table),
 * so there is never a cross-type fallback. Owns NO connections — SQL
 * connections stay in DatabaseManager (PdoStore borrows them via its
 * read/write roles); MongoStore wraps the Mongo driver directly.
 *
 * RequestScoped: registry entries are config-like, but the request-scoped
 * reset keeps tests clean and lets tenant config swap safely in workers.
 */
final class StoreManager implements RequestScoped
{
    /** @var array<string, array<string, Store>> type => role => Store (or factory callable) */
    private array $stores = [];

    /** @var array<string, ?string> type => default role (null = none) */
    private array $defaults = [];

    /**
     * Register a Store for a (type, role) pair. Types are 'sql' and 'mongo';
     * an unknown type throws immediately (typo-proof at registration time).
     */
    public function set(string $type, string $role, Store|callable $store): static
    {
        $this->stores[$type][$role] = $store;
        return $this;
    }

    /**
     * Register the default role for a type: getOrDefault(type, missingRole)
     * falls back to it. get(type, role) stays strict — no default fallback.
     */
    public function setDefault(string $type, string $role): static
    {
        $this->defaults[$type] = $role;
        return $this;
    }

    public function has(string $type, string $role): bool
    {
        return isset($this->stores[$type][$role]);
    }

    /**
     * Strict resolution: exact (type, role) entry or an exception — never
     * the type's default role, never the other type's map.
     */
    public function get(string $type, string $role): Store
    {
        return $this->resolve($type, $role, fallBackToDefault: false);
    }

    /**
     * Lenient resolution: exact (type, role) entry, else the type's default
     * role, else an exception.
     */
    public function getOrDefault(string $type, string $role): Store
    {
        return $this->resolve($type, $role, fallBackToDefault: true);
    }

    /**
     * All registered roles for a type.
     * @return list<string>
     */
    public function roles(string $type): array
    {
        return \array_keys($this->stores[$type] ?? []);
    }

    public function defaultRole(string $type): ?string
    {
        return $this->defaults[$type] ?? null;
    }

    public function resetState(): void
    {
        // Keep config; factories may re-resolve tenant specifics per request.
    }

    /**
     * Shared resolution: map lookup, optional default fallback, factory
     * expansion. The type is validated before any map access, so the two
     * maps can never mix.
     */
    private function resolve(string $type, string $role, bool $fallBackToDefault): Store
    {
        $map   = $this->stores[$type] ?? [];
        $entry = $map[$role] ?? null;

        if ($entry === null && $fallBackToDefault) {
            $default = $this->defaults[$type] ?? null;
            if ($default !== null && $default !== $role) {
                $entry = $map[$default] ?? null;
            }
        }

        if ($entry === null) {
            throw new \RuntimeException(
                "Store role '{$role}' not configured for type '{$type}'"
            );
        }

        if ($entry instanceof Store) {
            return $entry;
        }

        return $this->stores[$type][$role] = $entry();
    }
}