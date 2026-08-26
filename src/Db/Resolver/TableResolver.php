<?php

declare(strict_types=1);

namespace Azera\Db\Resolver;

/**
 * Resolves a logical model/table name into a concrete source descriptor.
 *
 * This is the single seam between the query builder and the data layer.
 * Implementations decide what a name like `User` or `users` means —
 * whether it maps to a real model class, a virtual mapping entry, or
 * a literal table name.
 *
 * The resolver is also the single source of truth for hydration: when
 * `modelClass` is non-null, the query builder passes it to `ResultSet`
 * so that `FETCH_CLASS` hydration is available. When it is null, only
 * fast array/object fetching is available.
 */
interface TableResolver
{
    /**
     * Resolve a logical name to a concrete source descriptor.
     *
     * @param string $name The logical model/table name (e.g. `User`, `users`, `App\Models\Order`).
     * @return array{source: string, schema: ?string, read: ?string, write: ?string, modelClass: ?class-string, idFields: ?array}
     *     - `source`:     The concrete table or view name.
     *     - `schema`:     Optional database schema (e.g. for PostgreSQL), or null.
     *     - `read`:       Optional read connection role, or null (falls back to AppContext default).
     *     - `write`:      Optional write connection role, or null (falls back to AppContext default).
     *     - `modelClass`: The fully-qualified model class name when this name maps to a real model,
     *                     or null for mapping/literal resolvers (no hydration).
     *     - `idFields`:   The primary key field names (for UPSERT conflict target), or null.
     * @throws ResolveException When the name cannot be resolved.
     */
    public function resolve(string $name): array;
}