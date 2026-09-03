<?php

namespace Azera\Orm\Attribute;

/**
 * One-to-many: the TARGET model's table holds the foreign key back to this
 * model. Property name = relation name (usually plural).
 *
 * Defaults: foreignKey = snake_case of THIS class's short name + '_id' on
 * the TARGET table, ownerKey = this model's first ID field.
 *
 * Default load strategy: SECOND QUERY by parent IDs (never a JOIN —
 * joined to-many duplicates parent rows).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class HasMany
{
    public function __construct(
        public string $target,
        public ?string $foreignKey = null,
        public ?string $ownerKey = null,
    )
    {
    }
}