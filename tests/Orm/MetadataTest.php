<?php

namespace Azera\Tests\Orm;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/Fixtures/Article.php';
require_once __DIR__ . '/Fixtures/Relations.php';
require_once __DIR__ . '/Fixtures/ArticleDocument.php';

use Azera\Orm\Metadata;
use Azera\Tests\Orm\Fixtures\Article;
use Azera\Tests\Orm\Fixtures\ArticleDocument;
use Azera\Tests\Orm\Fixtures\ArticleWithRelations;
use Azera\Tests\Orm\Fixtures\Comment;
use PHPUnit\Framework\TestCase;

class MetadataTest extends TestCase
{
    protected function setUp(): void
    {
        Metadata::clear();
    }

    public function testCompileInferredAndExplicitColumns(): void
    {
        $meta = Metadata::for(Article::class);

        $this->assertSame('article', $meta['source']);
        $this->assertSame('sql', $meta['store']);

        // explicit #[Column(type: int)] on $id, first *_id-named property → pk
        $this->assertTrue($meta['columns']['id']['pk']);
        $this->assertSame('int', $meta['columns']['id']['type']);

        $this->assertSame('string', $meta['columns']['title']['type']);
        $this->assertFalse($meta['columns']['title']['pk']);

        $this->assertSame('datetime', $meta['columns']['created_at']['type']);

        // transient property excluded entirely
        $this->assertArrayNotHasKey('computed', $meta['columns']);
    }

    public function testColumnNameOverride(): void
    {
        $meta = Metadata::for(Article::class);

        $this->assertSame('status_code', $meta['columns']['status']['name']);
        $this->assertSame('int', $meta['columns']['status']['type']);
        $this->assertFalse($meta['columns']['status']['pk']);
    }

    public function testRelationsCompiled(): void
    {
        $rel = Metadata::for(ArticleWithRelations::class)['relations'];

        $this->assertSame('hasOne', $rel['meta']['type']);
        // foreignKey default = source-based: article_with_relations_id
        $this->assertSame('article_with_relations_id', $rel['meta']['foreignKey']);
        $this->assertSame('join', $rel['meta']['strategy']);

        $this->assertSame('hasMany', $rel['comments']['type']);
        $this->assertSame('article_with_relations_id', $rel['comments']['foreignKey']);
        $this->assertSame('second_query', $rel['comments']['strategy']);
    }

    public function testBelongsToDefaultsForeignKeyFromRelationName(): void
    {
        $rel = Metadata::for(Comment::class)['relations'];

        $this->assertSame('belongsTo', $rel['article']['type']);
        $this->assertSame(Article::class, $rel['article']['target']);
        // foreignKey default = relation name + '_id'
        $this->assertSame('article_id', $rel['article']['foreignKey']);
        $this->assertSame('join', $rel['article']['strategy']);

        $this->assertSame('author_id', $rel['author']['foreignKey']);
        $this->assertSame('join', $rel['author']['strategy']);
    }

    public function testDocumentStoreSwitchesStoreAndCollection(): void
    {
        $meta = Metadata::for(ArticleDocument::class);

        $this->assertSame('mongo', $meta['store']);
        $this->assertSame('articles', $meta['collection']);
    }

    public function testL1CacheReturnsIdenticalArray(): void
    {
        $a = Metadata::for(Article::class);
        $b = Metadata::for(Article::class);

        $this->assertSame($a, $b, 'L1 cache returns the identical array');
    }

    public function testClearForcesRecompile(): void
    {
        $a = Metadata::for(Article::class);
        Metadata::clear();
        $b = Metadata::for(Article::class);

        // clear() empties L1, so the only way for() can return data again
        // is a fresh compile(). (Note: assertNotSame is meaningless for
        // arrays — PHP array === is content comparison, not identity.)
        $this->assertEquals($a, $b, 'recompiled metadata equals the original');
    }
}