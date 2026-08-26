<?php

declare(strict_types=1);

namespace Azera\Db\Resolver;

/**
 * Resolves names as literal table names with no model hydration.
 *
 * This is the resolver used by {@see \Azera\Db\Query::raw()}.
 * Every name is treated as-is — no class lookups, no mapping lookups,
 * no connection overrides, no hydration.
 */
class LiteralResolver implements TableResolver
{
    public function resolve(string $name): array
    {
        // Split schema.table into schema and table, if present
        $parts = explode('.', $name, 2);
        return [
            'source'     => $parts[1] ?? $name,
            'schema'     => isset($parts[1]) ? $parts[0] : null,
            'read'       => null,
            'write'      => null,
            'modelClass' => null,
            'idFields'   => null,
        ];
    }
}