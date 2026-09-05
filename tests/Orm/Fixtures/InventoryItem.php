<?php

namespace Azera\Tests\Orm\Fixtures;

use Azera\Orm\Model;
use Azera\Orm\Attribute\Column;
use Azera\Orm\Attribute\Connection;
use Azera\Orm\Attribute\Table;

/**
 * All-in-one SQL attribute fixture: #[Table] (name + schema),
 * #[Connection] (split read/write roles), #[Column(pk: true)] composite
 * key, plus a renamed, non-PK *_id column excluded from the key.
 */
#[Table(name: 'inventory_items', schema: 'warehouse')]
#[Connection(read: 'replica', write: 'primary')]
class InventoryItem extends Model
{
    #[Column(type: 'int', pk: true)]
    public $tenant_id;

    #[Column(type: 'int', pk: true)]
    public $item_id;

    public $name;

    /** Renamed *_id column that is NOT part of the key — pk: false guard. */
    #[Column(name: 'external_ref_id', pk: false)]
    public $externalRef;
}