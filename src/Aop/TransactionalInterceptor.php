<?php

namespace Azera\Aop;

use Azera\Db\Database;
use Azera\Db\DatabaseManager;
use ReflectionMethod;
use Throwable;

/**
 * Intercepts methods marked with {@see Transactional} and wraps them
 * in a database transaction.
 *
 * - Begins a transaction (or savepoint if nested) before the method
 * - Commits on success
 * - Rolls back on any Throwable, then re-throws
 *
 * The connection role can be specified via the attribute argument
 * (defaults to the write connection).
 */
class TransactionalInterceptor implements InterceptorInterface
{
    public function __construct(
        private DatabaseManager $dbManager,
    )
    {
    }

    public function intercept(object $target, ReflectionMethod $method, array $args, callable $next): mixed
    {
        // Resolve the advice attribute to get the connection role
        $advice = $this->getAdvice($method);
        $role   = $advice?->connection;

        $db = $this->resolveDatabase($role);

        $db->begin(true);
        try {
            $result = $next($args);
            $db->commit();
            return $result;
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    private function getAdvice(ReflectionMethod $method): ?Transactional
    {
        $attrs = $method->getAttributes(Transactional::class);
        if ($attrs === []) {
            return null;
        }
        return $attrs[0]->newInstance();
    }

    private function resolveDatabase(?string $role): Database
    {
        if ($role !== null) {
            return $this->dbManager->get($role);
        }

        return $this->dbManager->getOrDefault('write');
    }
}
