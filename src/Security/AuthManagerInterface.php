<?php

declare(strict_types=1);

namespace Azera\Security;

/**
 * Contract for an authentication manager.
 *
 * The manager is a registry of named guards (e.g. "web" for session-based
 * auth, "api" for token-based auth). It owns the currently active guard
 * and exposes a uniform API for the most common operations.
 *
 * Concrete guards live in the companion package `azera/auth`. The
 * framework ships contracts only so applications can be built against
 * the interface without pulling in an implementation.
 */
interface AuthManagerInterface
{
    /**
     * Register a guard under a name.
     *
     * @param string         $name  Guard identifier (e.g. "web", "api").
     * @param GuardInterface $guard The guard instance.
     */
    public function addGuard(string $name, GuardInterface $guard): void;

    /**
     * Set the guard that should be used for subsequent calls.
     *
     * @param string $name Guard identifier previously registered.
     * @throws \InvalidArgumentException If no guard is registered under $name.
     */
    public function guard(?string $name = null): GuardInterface;

    /**
     * Get the currently active guard.
     */
    public function currentGuard(): GuardInterface;

    /**
     * Attempt to authenticate a set of credentials against the
     * active guard.
     *
     * @param array<string,mixed> $credentials Credential bag (e.g.
     *   `['email' => …, 'password' => …]`).
     * @return bool True if authentication succeeded.
     */
    public function attempt(array $credentials): bool;

    /**
     * Log the current user out via the active guard.
     */
    public function logout(): void;

    /**
     * Check whether a user is currently authenticated via the
     * active guard.
     */
    public function check(): bool;

    /**
     * Get the authenticated user identifier, or null if not
     * authenticated.
     */
    public function id(): mixed;
}