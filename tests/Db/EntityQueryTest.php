<?php

namespace Azera\Tests\Db;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/TestDatabase.php';
require_once __DIR__ . '/../Orm/Fixtures/Article.php';
require_once __DIR__ . '/../Orm/Fixtures/Relations.php';

use Azera\AppContext;
use Azera\Db\Query;
use Azera\Orm\FastHydrator;
use Azera\Orm\Metadata;
use Azera\Tests\Db\TestDatabase;
use Azera\Tests\Orm\Fixtures\Article;
use Azera\Tests\Orm\Fixtures\ArticleWithRelations;
use Azera\Tests\Orm\Fixtures\Author;
use Azera\Tests\Orm\Fixtures\Comment;
use PHPUnit\Framework\TestCase;

/**
 * Unified read path: Query as the single builder for raw tables, model
 * mapping, heap hydration (entities/firstEntity) and joined models
 * (with() + to-many batched second queries).
 */
class EntityQueryTest extends TestCase
{
    private TestDatabase $db;

    protected function setUp(): void
    {
        AppContext::setInstance(new AppContext());
        FastHydrator::clear();
        Metadata::clear();
        $this->db = new TestDatabase('pgsql');
        AppContext::instance()->dbManager()->set('read', $this->db);
        AppContext::instance()->dbManager()->set('write', $this->db);
    }

    private function articleRow(int $id, string $title = 'T'): array
    {
        return ['id' => $id, 'title' => $title, 'created_at' => null, 'status_code' => null];
    }

    /* ------------------------------------------------- heap hydration */

    public function testEntitiesHydratesHeapTrackedEntities(): void
    {
        $this->db->setMockResults([[$this->articleRow(1, 'A'), $this->articleRow(2, 'B')]]);

        $items = Query::modelFor(\Azera\Tests\Orm\Fixtures\Article::class, $this->db)->entities();

        $this->assertCount(2, $items);
        $this->assertInstanceOf(\Azera\Tests\Orm\Fixtures\Article::class, $items[0]);
        $this->assertSame('A', $items[0]->title);

        // Attached to the request-scoped heap as MANAGED.
        $heap = AppContext::instance()->heap();
        $node = $heap->findById(\Azera\Tests\Orm\Fixtures\Article::class, ['id' => 1]);
        $this->assertNotNull($node);
        $this->assertSame('A', $node->data['title']);
    }

    public function testEntitiesIdentitySameRowSameObject(): void
    {
        $this->db->setMockResults([
            [$this->articleRow(7, 'first')],
            [$this->articleRow(7, 'second')] // same PK, later query
        ]);

        $q = Query::modelFor(\Azera\Tests\Orm\Fixtures\Article::class, $this->db);

        [$a] = $q->entities();
        [$b] = $q->entities();

        $this->assertSame($a, $b, 'heap identity map must return the same instance for the same PK');
        // Snapshot from the FIRST load is kept — no silent overwrite.
        $this->assertSame('first', $a->title);
    }

    public function testFirstEntityReturnsFirstOrNull(): void
    {
        $this->db->setMockResults([[$this->articleRow(5, 'X')]]);
        $item = Query::modelFor(\Azera\Tests\Orm\Fixtures\Article::class, $this->db)
            ->where('id', '=', 5)
            ->firstEntity();
        $this->assertSame(5, $item->id);
        $this->assertSame('X', $item->title);

        $this->db->setMockResults([[]]);
        $none = Query::modelFor(\Azera\Tests\Orm\Fixtures\Article::class, $this->db)
            ->where('id', '=', 999)
            ->firstEntity();
        $this->assertNull($none);
    }

    public function testEntitiesRequiresModelMode(): void
    {
        $this->expectException(\LogicException::class);
        Query::raw($this->db)->table('users')->entities();
    }

    /* ------------------------------------------------ field validation */

    public function testUnknownWhereFieldThrows(): void
    {
        $q = Query::modelFor(\Azera\Tests\Orm\Fixtures\Article::class, $this->db);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('titel'); // typo
        $q->where('titel', '=', 'x');
    }

    public function testUnknownOrderByFieldThrows(): void
    {
        $q = Query::modelFor(\Azera\Tests\Orm\Fixtures\Article::class, $this->db);
        $this->expectException(\InvalidArgumentException::class);
        $q->orderBy('created_at DESC, nonsense');
    }

    public function testKnownFieldsPassValidation(): void
    {
        $q = Query::modelFor(\Azera\Tests\Orm\Fixtures\Article::class, $this->db);
        // Must NOT throw (status is a #[Column(name: status_code)] alias).
        $q->where('status', '=', 1)->orderBy('created_at DESC');
        $this->assertTrue(true);
    }

    /* --------------------------------------------- operator-aware where */

    public function testOperatorWhereBindsParams(): void
    {
        $this->db->setMockResults([[$this->articleRow(1)]]);

        $row = Query::modelFor(\Azera\Tests\Orm\Fixtures\Article::class, $this->db)
            ->where('id', '>', 10)
            ->firstEntity();

        $q = $this->db->getLastQuery();
        $this->assertStringContainsString('"id" > :w0', $q['sql']);
        $this->assertSame(10, $q['params']['w0'] ?? null);
    }

    public function testEmptyInListCompilesToConstant(): void
    {
        $sql = Query::raw($this->db)->table('users')->where('id', 'IN', [])->toSql();
        $this->assertStringContainsString('1=0', $sql);
    }

    public function testNotEmptyInListBindsEachValue(): void
    {
        $this->db->setMockResults([[]]);

        Query::raw($this->db)->table('users')->where('id', 'IN', [3, 4, 5])->select();

        $q = $this->db->getLastQuery();
        $this->assertStringContainsString('IN (:w0, :w1, :w2)', $q['sql']);
        $this->assertSame([3, 4, 5], array_values($q['params']));
    }

    public function testNullEqualityUsesIsNull(): void
    {
        $sql = Query::raw($this->db)->table('users')->where('deleted_at', '=', null)->toSql();
        $this->assertStringContainsString('IS NULL', $sql);
    }

    public function testNotWhereWithOperatorPrefixesNot(): void
    {
        $sql = Query::raw($this->db)->table('users')->notWhere('id', '=', 5)->toSql();
        $this->assertStringContainsString('NOT ("id" = 5)', $sql);
    }

    /* ---------------------------------------------- count()/first() etc */

    public function testCountIgnoresOrderAndLimit(): void
    {
        $this->db->setMockResults([[['cnt' => 42]]]);

        $count = Query::modelFor(\Azera\Tests\Orm\Fixtures\Article::class, $this->db)
            ->where('id', '>', 1)
            ->orderBy('id')
            ->limit(5)
            ->count();

        $this->assertSame(42, $count);
        $sql = $this->db->getLastQuery()['sql'];
        $this->assertStringContainsString('COUNT(*)', $sql);
        $this->assertStringNotContainsString('ORDER BY', $sql);
        $this->assertStringNotContainsString('LIMIT', $sql);
    }

    /* --------------------------------------------------- joined models */

    public function testToOneJoinHydratesAndWires(): void
    {
        // One row: comment 1 by author 2.
        $this->db->setMockResults([
            [
                [
                    'comment__id'          => 1,
                    'comment__body'        => 'Hey',
                    'comment__article_id'  => 5,
                    'comment__author_id'   => 2,
                    'comment_author__id'   => 2,
                    'comment_author__name' => 'Ada',
                ],
            ]
        ]);

        /** @var Comment $c */
        $c = Query::modelFor(Comment::class, $this->db)
            ->with('author')
            ->firstEntity();
        $this->assertInstanceOf(Comment::class, $c);
        $this->assertInstanceOf(Author::class, $c->author);
        $this->assertSame('Ada', $c->author->name);
        // Joined child is on the shared heap too.
        $this->assertNotNull(AppContext::instance()->heap()->findById(Author::class, ['id' => 2]));
    }

    public function testToManyUsesSingleBatchedQuery(): void
    {
        // Main joined query returns two articles. Alias = source convention
        // of ArticleWithRelations (pluralization off): article_with_relations.
        $this->db->setMockResults([
            [
                [
                    'article_with_relations__id'          => 1,
                    'article_with_relations__title'       => 'A',
                    'article_with_relations__created_at'  => null,
                    'article_with_relations__status_code' => null,
                ],
                [
                    'article_with_relations__id'          => 2,
                    'article_with_relations__title'       => 'B',
                    'article_with_relations__created_at'  => null,
                    'article_with_relations__status_code' => null,
                ],
            ],
            // Single batched second query result: two comments for article 1.
            // FK convention: <source>_id => article_with_relations_id.
            [
                ['id' => 10, 'body' => 'c1', 'article_with_relations_id' => 1, 'author_id' => null],
                ['id' => 11, 'body' => 'c2', 'article_with_relations_id' => 1, 'author_id' => null],
            ],
        ]);

        $articles = Query::modelFor(ArticleWithRelations::class, $this->db)
            ->with('comments')
            ->orderBy('id')
            ->entities();

        $this->assertCount(2, $articles);
        $this->assertCount(2, $articles[0]->comments);
        $this->assertSame([], $articles[1]->comments);

        // Exactly TWO statements total: joined SELECT + one batched IN.
        $this->assertCount(2, $this->db->queries);
        $second = $this->db->queries[1]['sql'];
        $this->assertStringContainsString('IN (?, ?)', $second);
        $this->assertStringContainsString('FROM "comment"', $second);
        // Params are the two root ids.
        $this->assertSame([1, 2], array_values($this->db->queries[1]['params']));
    }

    public function testFirstOnEagerQueryReturnsRootEntity(): void
    {
        $this->db->setMockResults([
            [
                [
                    'comment__id'          => 1,
                    'comment__body'        => 'Hey',
                    'comment__article_id'  => 5,
                    'comment__author_id'   => 2,
                    'comment_author__id'   => 2,
                    'comment_author__name' => 'Ada',
                ],
            ],
        ]);

        /** @var Comment $c */
        $c = Query::modelFor(Comment::class, $this->db)
            ->with('author')
            ->where('comment.id', '=', 1)
            ->first();

        $this->assertInstanceOf(Comment::class, $c);
        $this->assertSame(1, $c->id);
    }

    public function testEagerQueryHonorsWhereAndLimit(): void
    {
        $this->db->setMockResults([
            [
                [
                    'comment__id'          => 1,
                    'comment__body'        => 'Hey',
                    'comment__article_id'  => 5,
                    'comment__author_id'   => 2,
                    'comment_author__id'   => 2,
                    'comment_author__name' => 'Ada',
                ]
            ]
        ]);

        Query::modelFor(Comment::class, $this->db)
            ->with('author')
            ->where('id', '=', 1)
            ->orderBy('id')
            ->limit(3, 6)
            ->entities();

        $sql = $this->db->getLastQuery()['sql'];
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 3', $sql);
        $this->assertStringContainsString('OFFSET 6', $sql);
    }
}