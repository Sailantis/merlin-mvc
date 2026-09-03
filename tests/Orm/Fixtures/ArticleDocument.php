<?php

namespace Azera\Tests\Orm\Fixtures;

use Azera\Orm\Attribute\Column;
use Azera\Orm\Attribute\Document;

/** Mongo document fixture. */
#[Document(collection: 'articles')]
class ArticleDocument
{
    #[Column(type: 'int')]
    public $_id;

    public $title;

    #[Column(type: 'json')]
    public $tags;
}