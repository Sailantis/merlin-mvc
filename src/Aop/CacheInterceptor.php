<?php

namespace Azera\Aop;

use Psr\SimpleCache\CacheInterface;
use ReflectionMethod;

/**
 * Intercepts methods marked with {@see Cache} and caches their return values.
 *
 * Cache key resolution:
 * 1. If a custom key template is provided, interpolate `{argName}` placeholders
 *    with the method arguments.
 * 2. Otherwise, build a key from the class name, method name, and a hash
 *    of the arguments.
 *
 * On a cache hit, the method is NOT executed — the cached value is returned.
 * On a miss, the method executes and the result is stored with the given TTL.
 */
class CacheInterceptor implements InterceptorInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function intercept(object $target, ReflectionMethod $method, array $args, callable $next): mixed
    {
        $advice = $this->getAdvice($method);
        $key    = $this->resolveKey($method, $args, $advice);

        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $result = $next($args);

        $this->cache->set($key, $result, $advice->ttl);

        return $result;
    }

    private function getAdvice(ReflectionMethod $method): Cache
    {
        $attrs = $method->getAttributes(Cache::class);
        if ($attrs === []) {
            return new Cache();
        }
        return $attrs[0]->newInstance();
    }

    private function resolveKey(ReflectionMethod $method, array $args, Cache $advice): string
    {
        if ($advice->key !== null) {
            return $this->interpolateKey($advice->key, $method, $args);
        }

        // Sanitize the class name — anonymous classes produce names
        // like "Pipeline.php:148$115" which contain invalid cache key chars.
        $className  = preg_replace('/[^A-Za-z0-9_]/', '_', $method->getDeclaringClass()->getShortName());
        $methodName = $method->getName();
        $argsHash   = md5(serialize($args));

        return "{$className}.{$methodName}.{$argsHash}";
    }

    private function interpolateKey(string $template, ReflectionMethod $method, array $args): string
    {
        $paramNames = [];
        foreach ($method->getParameters() as $i => $param) {
            $paramNames['{' . $param->getName() . '}'] = $args[$i] ?? '';
        }

        return strtr($template, $paramNames);
    }
}