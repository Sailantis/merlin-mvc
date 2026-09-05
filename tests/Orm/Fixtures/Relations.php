<?php

namespace Azera\Tests\Orm\Fixtures;

use Azera\Orm\Model;
use Azera\Orm\Attribute\BelongsTo;
use Azera\Orm\Attribute\HasMany;
use Azera\Orm\Attribute\HasOne;

/**
 * Relations fixture: Article (parent) with Comment (to-many, second-query
 * strategy) and Author/Meta (to-one, join strategy).
 */
class Comment extends Model
{
    public $id;

    #[BelongsTo(target: Article::class)]
    public $article;

    #[BelongsTo(target: Author::class)]
    public $author;

    public $body;
}

class Author extends Model
{
    public $id;

    public $name;
}

class Meta extends Model
{
    public $id;

    #[HasOne(target: Article::class)]
    public $article;

    public $value;
}

class Tag extends Model
{
    public $id;

    #[HasMany(target: Article::class)]
    public $articles;

    public $label;
}

class ArticleWithRelations extends Model
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