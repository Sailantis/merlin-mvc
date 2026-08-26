<?php

declare(strict_types=1);

namespace Azera\Db\Resolver;

use Azera\Core\ModelMapping;

/**
 * Resolves logical names via a {@see ModelMapping} configuration.
 *
 * Each entry in the mapping provides a `source` (table name), optional
 * `schema`, and optional connection roles (`connection` for both read+write,
 * or individual `read`/`write` overrides). No model hydration is available —
 * `modelClass` and `idFields` are always null.
 */
class MappingResolver implements TableResolver
{
    public function __construct(
        private ModelMapping $mapping,
    ) {}

    public function resolve(string $name): array
    {
        $entry = $this->mapping->get($name);

        if ($entry === null) {
            throw new ResolveException("Model '{$name}' not found in model mapping");
        }

        $connection = $entry['connection'] ?? null;
        $read       = $entry['read'] ?? $connection;
        $write      = $entry['write'] ?? $connection;

        return [
            'source'     => $entry['source'],
            'schema'     => $entry['schema'] ?? null,
            'read'       => $read,
            'write'      => $write,
            'modelClass' => null,
            'idFields'   => null,
        ];
    }
}