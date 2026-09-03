<?php

namespace Azera\Orm\Storage;

use Azera\Lifecycle\RequestScoped;

/**
 * Service registry: role -> Store instance. Owns NO connections — SQL
 * connections stay in DatabaseManager; PdoStore borrows them via its
 * read/write roles. MongoStore wraps the Mongo driver directly.
 *
 * RequestScoped: registry entries are config-like, but the request-scoped
 * reset keeps tests clean and lets tenant config swap safely in workers.
 */
final class StoreManager implements RequestScoped
{
    /** @var array<string, Store> role => Store instance (or factory callable) */
    private array $stores = [];

    /** @var ?string default role */
    private ?string $default = null;

    public function set(string $role, Store|callable $store): static
    {
        $this->stores[$role] = $store;
        return $this;
    }

    public function setDefault(string $role): static
    {
        $this->default = $role;
        return $this;
    }

    public function has(string $role): bool
    {
        return isset($this->stores[$role]);
    }

    public function get(string $role): Store
    {
        $entry = $this->stores[$role] ?? null;

        if ($entry === null) {
            throw new \RuntimeException("Store role '{$role}' not configured");
        }

        if ($entry instanceof Store) {
            return $entry;
        }

        return $this->stores[$role] = $entry();
    }

    /**
     * Resolve a role or fall back to the default.
     */
    public function getOrDefault(string $role): Store
    {
        if ($this->has($role)) {
            return $this->get($role);
        }

        return $this->get($this->default ?? 'default');
    }

    /**
     * All registered roles.
     * @return list<string>
     */
    public function roles(): array
    {
        return \array_keys($this->stores);
    }

    public function defaultRole(): ?string
    {
        return $this->default;
    }

    public function resetState(): void
    {
        // Keep config; factories may re-resolve tenant specifics per request.
    }
}