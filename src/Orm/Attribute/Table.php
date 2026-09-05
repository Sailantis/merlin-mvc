<?php

namespace Azera\Orm\Attribute;

/**
 * Class-level table configuration for SQL models.
 *
 * Declares the table name and/or database schema (PostgreSQL et al.)
 * declaratively instead of overriding source()/schema(). Compiled into
 * metadata, so the Store seam, the query builder (via ModelResolver) and
 * the model facade defaults all see it with zero runtime cost.
 *
 * Precedence: a source()/schema() override on the model still wins over
 * the attribute (dynamic > static); the attribute wins over the naming
 * convention.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Table
{
    public function __construct(
        public ?string $name = null,
        public ?string $schema = null,
    )
    {
    }
}