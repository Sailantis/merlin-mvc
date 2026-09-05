<?php

namespace Azera\Orm\Attribute;

/**
 * Declares a property as a persistent column.
 *
 * Everything is optional: an unattributed declared property is still a
 * column with inferred defaults (name = property name, type 'string').
 * The attribute exists to override those defaults.
 *
 * `pk` explicitly marks (or excludes) a primary key: true marks the
 * column as part of the PK (composite keys = multiple marked columns);
 * false excludes a column the *_id name convention would wrongly mark.
 * An idFields() override on the model still wins over these marks.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public string $type = 'string',
        public ?string $name = null,
        public bool $nullable = false,
        public bool $transient = false,
        public ?bool $pk = null,
    )
    {
    }
}