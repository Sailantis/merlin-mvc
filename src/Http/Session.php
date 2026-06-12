<?php
namespace Merlin\Http;

/**
 * Wrapper around a PHP session array that provides typed accessors and
 * a clean API for reading, writing, and clearing session data.
 */
class Session
{
    /**
     * Create a new Session backed by the given store reference.
     *
     * @param array $store Reference to the underlying session array (typically $_SESSION).
     */
    public function __construct(private array &$store)
    {
    }

    /**
     * Retrieve a value from the session.
     *
     * @param string $key     Session key.
     * @param mixed  $default Value to return when the key is not set.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }

    /**
     * Store a value in the session.
     *
     * @param string $key   Session key.
     * @param mixed  $value Value to store.
     */
    public function set(string $key, mixed $value): void
    {
        $this->store[$key] = $value;
    }

    /**
     * Remove a key from the session.
     *
     * @param string $key Session key to unset.
     */
    public function remove(string $key): void
    {
        unset($this->store[$key]);
    }

    /**
     * Check whether a key exists in the session.
     *
     * @param string $key Session key.
     * @return bool True if the key is set and not null.
     */
    public function has(string $key): bool
    {
        return isset($this->store[$key]);
    }

    /**
     * Remove all data from the session.
     */
    public function clear(): void
    {
        $this->store = [];
    }
}