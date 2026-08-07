<?php

declare(strict_types=1);

namespace Azera\Security;

use Psr\SimpleCache\CacheInterface;

/**
 * Simple, cache-backed rate limiter.
 *
 * Each call to {@see limit()} records a hit for the given key and
 * returns whether the caller is still within the configured budget.
 * Storage is delegated to a {@see CacheInterface} instance so the
 * limiter works with {@see \Azera\Cache\ArrayCache} in development
 * and a distributed cache (Redis, Memcached) in production.
 *
 * The limiter uses a fixed time-window strategy: a counter is stored
 * with a TTL equal to the window size and incremented on every hit.
 * When the entry expires the window resets automatically.
 */
class RateLimiter
{
    /**
     * @param CacheInterface $cache The cache used to persist counters.
     */
    public function __construct(
        private CacheInterface $cache,
    ) {}

    /**
     * Record a hit for `$key` and report whether the limit is exceeded.
     *
     * @param string $key        Identifier for the rate-limited resource
     *   (e.g. `ip:127.0.0.1`, `login:user@example.com`).
     * @param int    $max        Maximum number of hits allowed within the
     *   window.
     * @param int    $perSeconds Size of the sliding window in seconds.
     * @return bool True if the request is within the limit (allowed),
     *   false if the limit has been exceeded (deny).
     */
    public function limit(string $key, int $max, int $perSeconds): bool
    {
        $cacheKey = $this->cacheKey($key);

        $current = $this->cache->get($cacheKey, 0);

        if (!is_int($current)) {
            $current = 0;
        }

        if ($current >= $max) {
            return false;
        }

        $this->cache->set($cacheKey, $current + 1, $perSeconds);

        return true;
    }

    /**
     * Get the number of hits recorded for `$key` within the current
     * window, or 0 when no entry exists.
     */
    public function hits(string $key): int
    {
        $value = $this->cache->get($this->cacheKey($key), 0);
        return is_int($value) ? $value : 0;
    }

    /**
     * Check whether `$key` has reached its limit without recording a
     * new hit.
     *
     * @param string $key  Identifier for the rate-limited resource.
     * @param int    $max  Maximum number of hits allowed.
     */
    public function isLimited(string $key, int $max): bool
    {
        return $this->hits($key) >= $max;
    }

    /**
     * Reset the counter for `$key`, clearing any recorded hits.
     */
    public function reset(string $key): void
    {
        $this->cache->delete($this->cacheKey($key));
    }

    /**
     * Build the namespaced cache key from a user-supplied identifier.
     *
     * Sanitizes characters that are invalid for {@see CacheInterface}
     * keys while preserving readability.
     */
    private function cacheKey(string $key): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $key);
        return 'rate_limit.' . $sanitized;
    }
}