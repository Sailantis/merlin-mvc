<?php

namespace Azera\Aop;

use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Throwable;

/**
 * Intercepts methods marked with {@see Retry} and retries them on failure.
 *
 * Retries up to `$times` attempts (including the first). Between retries,
 * sleeps for `$backoff` milliseconds. If all attempts fail, the last
 * exception is re-thrown.
 *
 * When used in the explicit {@see Pipeline} (no attribute), the constructor
 * defaults for `times` and `backoff` are used.
 */
class RetryInterceptor implements InterceptorInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null,
        private int $defaultTimes = 3,
        private int $defaultBackoff = 0,
    ) {}

    public function intercept(object $target, ReflectionMethod $method, array $args, callable $next): mixed
    {
        $advice    = $this->getAdvice($method);
        $attempts  = $advice->times;
        $backoffMs = $advice->backoff;

        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $next($args);
            } catch (Throwable $e) {
                $lastException = $e;

                if ($attempt < $attempts) {
                    $this->logger?->debug(
                        "Retrying {$method->getName()} (attempt {$attempt}/{$attempts})",
                        ['exception' => $e->getMessage(), 'backoff_ms' => $backoffMs]
                    );

                    if ($backoffMs > 0) {
                        usleep($backoffMs * 1000);
                    }
                }
            }
        }

        throw $lastException;
    }

    private function getAdvice(ReflectionMethod $method): Retry
    {
        $attrs = $method->getAttributes(Retry::class);
        if ($attrs === []) {
            // No attribute (explicit pipeline) — use constructor defaults.
            return new Retry(times: $this->defaultTimes, backoff: $this->defaultBackoff);
        }
        return $attrs[0]->newInstance();
    }
}