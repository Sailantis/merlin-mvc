<?php

namespace Azera\Tests\Orm\Fixtures;

use Azera\Orm\Attribute\BelongsTo;
use Azera\Orm\Attribute\Column;
use Azera\Orm\Attribute\Document;
use Azera\Orm\Attribute\HasMany;
use Azera\Orm\Attribute\HasOne;

/** SQL model exercising inferred columns + explicit attributes. */
class Article
{
    #[Column(type: 'int')]
    public $id;

    public $title;

    #[Column(type: 'datetime')]
    public $created_at;

    #[Column(transient: true)]
    public $computed;

    #[Column(name: 'status_code', type: 'int')]
    public $status;
}