<?php

declare(strict_types=1);

namespace Azera\Security;

/**
 * Contract for an authentication guard.
 *
 * A guard is responsible for a single authentication strategy: validating
 * credentials, persisting the authenticated state (session, token, etc.),
 * and exposing the authenticated user.
 *
 * Implementations live in the companion package `azera/auth` (session
 * guard, token guard, JWT guard). The framework ships the contract so
 * application code can depend on the interface.
 */
interface GuardInterface
{
    /**
     * Attempt to authenticate with the given credentials.
     *
     * On success the authenticated state MUST be persisted so that
     * subsequent calls to {@see check()} return true.
     *
     * @param array<string,mixed> $credentials Credential bag (e.g.
     *   `['email' => …, 'password' => …]`).
     * @return bool True if authentication succeeded.
     */
    public function attempt(array $credentials): bool;

    /**
     * Check whether a user is currently authenticated.
     */
    public function check(): bool;

    /**
     * Get the authenticated user, or null if not authenticated.
     *
     * The shape of the user object is implementation-defined.
     */
    public function user(): mixed;

    /**
     * Get the authenticated user's identifier, or null if not
     * authenticated.
     */
    public function id(): mixed;

    /**
     * Log the current user out, clearing any persisted state.
     */
    public function logout(): void;
}