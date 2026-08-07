<?php

declare(strict_types=1);

namespace Azera\Security;

/**
 * Hashes and verifies passwords using PHP's native password hashing API.
 *
 * A thin, testable wrapper around {@see password_hash()} and
 * {@see password_verify()}. The algorithm and cost are configurable
 * so applications can tune them from a single place. Implementations
 * MUST NOT store or log plain passwords.
 */
class Hasher
{
    /**
     * @param string|int|null $algo    Hashing algorithm. Defaults to
     *   {@see PASSWORD_DEFAULT} when null. Pass a named algorithm constant
     *   (e.g. {@see PASSWORD_BCRYPT}) or null.
     * @param array<string,mixed> $options Options forwarded to
     *   {@see password_hash()} (e.g. `['cost' => 12]` for bcrypt).
     */
    public function __construct(
        private string|int|null $algo = null,
        private array $options = [],
    ) {}

    /**
     * Hash a plain-text password.
     *
     * @param string $password The plain password to hash.
     * @return string The hash, including the algorithm and cost.
     */
    public function make(string $password): string
    {
        return password_hash($password, $this->algo ?? \PASSWORD_DEFAULT, $this->options);
    }

    /**
     * Verify a plain password against a stored hash.
     *
     * @param string $password The plain password to check.
     * @param string $hash      The stored hash.
     * @return bool True if the password matches the hash.
     */
    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check whether a stored hash should be rehashed because the
     * algorithm or cost no longer match the current configuration.
     *
     * @param string $hash The stored hash.
     * @return bool True if the hash needs rehashing.
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algo ?? \PASSWORD_DEFAULT, $this->options);
    }

    /**
     * Generate a random token of the given length in raw bytes,
     * returned as a hex-encoded string.
     *
     * @param int $length Number of random bytes (default 32).
     * @return string Hex-encoded token (2 × $length characters).
     * @throws \Exception If sufficient random bytes cannot be generated.
     */
    public function token(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}