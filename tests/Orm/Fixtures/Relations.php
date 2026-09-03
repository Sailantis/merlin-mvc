<?php

namespace Azera\Tests\Orm\Fixtures;

use Azera\Orm\Attribute\BelongsTo;
use Azera\Orm\Attribute\HasMany;
use Azera\Orm\Attribute\HasOne;

/**
 * Relations fixture: Article (parent) with Comment (to-many, second-query
 * strategy) and Author/Meta (to-one, join strategy).
 */
class Comment
{
    public $id;

    #[BelongsTo(target: Article::class)]
    public $article;

    #[BelongsTo(target: Author::class)]
    public $author;

    public $body;
}

class Author
{
    public $id;

    public $name;
}

class Meta
{
    public $id;

    #[HasOne(target: Article::class)]
    public $article;

    public $value;
}

class Tag
{
    public $id;

    #[HasMany(target: Article::class)]
    public $articles;

    public $label;
}

class ArticleWithRelations
{
    public $id;

    public $title;

    #[HasOne(target: Meta::class)]
    public $meta;

    #[HasMany(target: Comment::class)]
    public $comments;

    #[BelongsTo(target: Author::class)]
    public $author;
}