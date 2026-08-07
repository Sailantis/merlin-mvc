<?php

namespace Azera\Cache;

use DateInterval;
use DateTimeInterface;
use DateTimeImmutable;
use Azera\Cache\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

/**
 * In-process cache backed by a PHP array.
 *
 * Useful for development, testing, and short-lived processes. Values
 * are stored with an expiry timestamp (or null for no expiry). No
 * serialization overhead — values are kept as live references.
 *
 * TTL handling:
 *   - int          → seconds from now
 *   - DateInterval → added to now
 *   - null         → no expiry (lives for the process lifetime)
 *
 * Keys are validated to be non-empty, alphanumeric + `._-`, max 64 chars.
 */
class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires: int|null}> */
    private array $data = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertKey($key);

        if (!isset($this->data[$key])) {
            return $default;
        }

        $entry = $this->data[$key];

        if ($entry['expires'] !== null && $entry['expires'] <= $this->now()) {
            unset($this->data[$key]);
            return $default;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        $this->assertKey($key);

        $this->data[$key] = [
            'value'   => $value,
            'expires' => $this->expiryToTimestamp($ttl),
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        $this->assertKey($key);
        unset($this->data[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->data = [];
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        $expires = $this->expiryToTimestamp($ttl);
        foreach ($values as $key => $value) {
            $this->assertKey((string) $key);
            $this->data[$key] = ['value' => $value, 'expires' => $expires];
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->assertKey($key);
            unset($this->data[$key]);
        }
        return true;
    }

    public function has(string $key): bool
    {
        $this->assertKey($key);

        if (!isset($this->data[$key])) {
            return false;
        }

        $entry = $this->data[$key];

        if ($entry['expires'] !== null && $entry['expires'] <= $this->now()) {
            unset($this->data[$key]);
            return false;
        }

        return true;
    }

    /**
     * Remove all expired entries. Called opportunistically; not required
     * for correctness since expired entries are lazily purged on access.
     */
    public function gc(): void
    {
        $now = $this->now();
        foreach ($this->data as $key => $entry) {
            if ($entry['expires'] !== null && $entry['expires'] <= $now) {
                unset($this->data[$key]);
            }
        }
    }

    protected function now(): int
    {
        return time();
    }

    private function expiryToTimestamp(int|\DateInterval|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof DateInterval) {
            return (new DateTimeImmutable())->add($ttl)->getTimestamp();
        }

        if ($ttl <= 0) {
            return 0; // already expired
        }

        return $this->now() + $ttl;
    }

    private function assertKey(string $key): void
    {
        if ($key === '' || strlen($key) > 64) {
            throw new InvalidArgumentException(
                "Cache key must be non-empty and at most 64 characters."
            );
        }

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $key)) {
            throw new InvalidArgumentException(
                "Cache key '$key' contains invalid characters. "
                . "Allowed: alphanumeric, '.', '_', '-'."
            );
        }
    }
}