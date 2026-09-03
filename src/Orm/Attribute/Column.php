<?php

namespace Azera\Orm\Attribute;

/**
 * Declares a property as a persistent column.
 *
 * Everything is optional: an unattributed declared property is still a
 * column with inferred defaults (name = property name, type 'string').
 * The attribute exists to override those defaults.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public string $type = 'string',
        public ?string $name = null,
        public bool $nullable = false,
        public bool $transient = false,
    )
    {
    }
}