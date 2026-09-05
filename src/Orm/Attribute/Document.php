<?php

namespace Azera\Orm\Attribute;

/**
 * Class-level marker: this object persists to MongoDB as a document.
 *
 * Presence of this attribute switches the compiled metadata to
 * store = 'mongo'; the optional argument names the collection
 * (default: the model's source() name). SQL models simply omit it.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Document
{
    public function __construct(
        public ?string $collection = null,
        public string $storeRole = 'default',
    )
    {
    }
}