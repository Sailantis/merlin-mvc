<?php

namespace Azera\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * No-op cache that stores nothing and always reports a miss.
 *
 * This is the default cache returned by {@see \Azera\AppContext::cache()}
 * when no concrete cache has been registered. It allows calling code to
 * safely use `$ctx->cache()->get('key', $default)` and always receive
 * the default, without null-checks.
 */
final class NullCache implements CacheInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        return true;
    }

    public function delete(string $key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $default;
        }
        return $result;
    }

    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return false;
    }
}