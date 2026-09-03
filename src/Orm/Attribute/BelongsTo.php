<?php

namespace Azera\Orm\Attribute;

/**
 * Many-to-one: THIS model's table holds the foreign key.
 *
 * Property name = relation name. Defaults: foreignKey = property name +
 * '_id' (e.g. property `author` → author_id), ownerKey = target's first
 * ID field.
 *
 * Default load strategy: SQL JOIN (to-one is always JOIN).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class BelongsTo
{
    public function __construct(
        public string $target,
        public ?string $foreignKey = null,
        public ?string $ownerKey = null,
    )
    {
    }
}