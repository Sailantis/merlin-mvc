<?php

namespace Azera\Orm\Attribute;

/**
 * One-to-one: the TARGET model's table holds the foreign key back to this model.
 *
 * Property name = relation name. Defaults: foreignKey = snake_case of THIS
 * class's short name + '_id' on the TARGET table, ownerKey = this model's
 * first ID field.
 *
 * Default load strategy: SQL JOIN (to-one is always JOIN).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class HasOne
{
    public function __construct(
        public string $target,
        public ?string $foreignKey = null,
        public ?string $ownerKey = null,
    )
    {
    }
}