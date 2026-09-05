<?php

namespace Azera\Orm\Attribute;

/**
 * Class-level read/write connection-role configuration for SQL models.
 *
 * Declarative alternative to setDefaultRole()/setDefaultReadRole()/
 * setDefaultWriteRole() bootstrap calls. Compiled into metadata, so
 * readRole()/writeRole() resolve it with zero runtime cost.
 *
 * `role` sets both directions; explicit `read`/`write` win over it:
 *
 *   #[Connection('primary')]                        // read + write
 *   #[Connection(read: 'replica', write: 'primary')] // split routing
 *
 * Runtime setters on the concrete model class still win over the
 * attribute (per-request tenancy needs an imperative escape hatch);
 * the attribute wins over the base-model global override and over the
 * role-name fallback ('read'/'write').
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Connection
{
    public function __construct(
        public ?string $role = null,
        public ?string $read = null,
        public ?string $write = null,
    )
    {
    }
}