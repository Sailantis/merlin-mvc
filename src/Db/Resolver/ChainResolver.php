<?php

declare(strict_types=1);

namespace Azera\Db\Resolver;

/**
 * Tries each resolver in order and returns the first successful result.
 *
 * If none of the chained resolvers can resolve the name, a
 * {@see ResolveException} is thrown. This preserves typo detection —
 * an unknown name like `Userr` that matches no model class and no mapping
 * entry will throw rather than silently fall back to a literal table.
 *
 * Typically used to combine a {@see ModelResolver} and a
 * {@see MappingResolver} as the AppContext default:
 *
 * ```php
 * new ChainResolver(new ModelResolver(), new MappingResolver($mapping))
 * ```
 */
class ChainResolver implements TableResolver
{
    /**
     * @param TableResolver ...$resolvers Resolvers to try, in order.
     */
    public function __construct(
        private array $resolvers,
    ) {}

    public function resolve(string $name): array
    {
        foreach ($this->resolvers as $resolver) {
            try {
                return $resolver->resolve($name);
            } catch (ResolveException) {
                continue;
            }
        }

        throw new ResolveException("Unable to resolve '{$name}' — no matching resolver in chain");
    }
}