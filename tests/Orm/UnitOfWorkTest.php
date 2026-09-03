<?php

namespace Azera\Tests\Orm;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Db/TestDatabase.php';
require_once __DIR__ . '/Fixtures/Article.php';
require_once __DIR__ . '/Fixtures/Relations.php';

use Azera\Orm\Heap;
use Azera\Orm\Node;
use Azera\Orm\UnitOfWork;
use Azera\Tests\Db\TestDatabase;
use Azera\Tests\Orm\Fixtures\Article;
use Azera\Tests\Orm\Fixtures\Author;
use Azera\Tests\Orm\Fixtures\Comment;
use PHPUnit\Framework\TestCase;

class UnitOfWorkTest extends TestCase
{
    private TestDatabase $db;
    private Heap $heap;
    private UnitOfWork $uow;

    protected function setUp(): void
    {
        $this->db   = new TestDatabase('pgsql');
        $this->heap = new Heap();
        $this->uow  = new UnitOfWork($this->heap, $this->db);
    }

    /**
     * TestDatabase also logs BEGIN/COMMIT — filter them out.
     */
    private function dataQueries(): array
    {
        return array_values(array_filter(
            $this->db->queries,
            fn($q) => !in_array($q['sql'], ['BEGIN', 'COMMIT', 'ROLLBACK'], true)
        ));
    }

    private function lastDataSql(): ?string
    {
        $q = $this->dataQueries();
        return $q === [] ? null : end($q)['sql'];
    }

    public function testFlushInsertPkSetUsesPlainInsert(): void
    {
        $a = new Article();
        $a->id    = 42;
        $a->title = 'Sentinel';

        $this->uow->persist($a);
        $this->uow->flush();

        $sql = $this->lastDataSql();
        $this->assertStringStartsWith('INSERT INTO "article"', $sql);
        $this->assertStringNotContainsString('RETURNING', $sql);
    }

    public function testFlushInsertAutoIncrementUsesReturningId(): void
    {
        $a = new Article();
        $a->title      = 'Auto';
        $a->created_at = '2026-09-03 10:00:00';
        $a->status     = 1;
        // all non-PK columns set, only id missing -> smallest payload
        $this->db->setMockResults([[['id' => 5]]]);

        $this->uow->persist($a);
        $this->uow->flush();

        $this->assertStringContainsString('RETURNING "id"', $this->lastDataSql());
        $this->assertSame(5, $a->id);
    }

    public function testFlushInsertMissingColumnsFallsBackToReturningAll(): void
    {
        $a = new Article();
        $a->title = 'Auto';
        $this->db->setMockResults([[['id' => 5, 'title' => 'Auto', 'status_code' => 0]]]);

        $this->uow->persist($a);
        $this->uow->flush();

        $this->assertStringContainsString('RETURNING *', $this->lastDataSql());
    }

    public function testUpdateOnlyChangedColumns(): void
    {
        $a = new Article();
        $a->id     = 1;
        $a->title  = 'One';
        $a->status = 0;

        $this->uow->persist($a);
        $this->uow->flush();
        $this->db->clearQueries();

        $a->title = 'Two';
        $title_only = ['title'];
        $this->uow->persist($a);
        $this->uow->flush();

        $sql = $this->lastDataSql();
        $this->assertStringStartsWith('UPDATE "article"', $sql);
        $this->assertStringContainsString('SET "title" = ?', $sql);
        $this->assertStringNotContainsString('status_code', $sql);
    }

    public function testCleanEntityProducesNoUpdate(): void
    {
        $a = new Article();
        $a->id    = 1;
        $a->title = 'Same';

        $this->uow->persist($a);
        $this->uow->flush();
        $this->db->clearQueries();

        $this->uow->persist($a); // unchanged → diff empty → no SQL
        $this->uow->flush();

        $this->assertSame([], $this->db->queries);
    }

    public function testDeleteUsesPkWhere(): void
    {
        $a = new Article();
        $a->id    = 1;
        $a->title = 'Doomed';

        $this->uow->persist($a);
        $this->uow->flush();
        $this->db->clearQueries();

        $this->uow->remove($a);
        $this->uow->flush();

        $sql = $this->lastDataSql();
        $this->assertStringStartsWith('DELETE FROM "article"', $sql);
        $this->assertStringContainsString('WHERE "id" = ?', $sql);
        $this->assertNull($this->heap->findById(Article::class, ['id' => 1]));
    }

    public function testBelongsToOwnerFlushesBeforeDependent(): void
    {
        $db = $this->db;
        $db->setMockResults([
            [['id' => 9]], // owner insert RETURNING
            [['id' => 9]]  // (unused second)
        ]);

        $author = new Author();
        $author->name = 'X';

        $c = new Comment();
        $c->author_id = null;
        $c->body      = 'B';

        $this->uow->persist($author);
        $this->uow->persist($c);
        $this->uow->flush();

        $q = $this->dataQueries();
        $this->assertCount(2, $q);
        $this->assertStringStartsWith('INSERT INTO "author"', $q[0]['sql']);
        $this->assertStringStartsWith('INSERT INTO "comment"', $q[1]['sql']);
    }

    public function testTransactionWrapsFlush(): void
    {
        $a = new Article();
        $a->id = 3;
        $this->uow->persist($a);
        $this->uow->flush();

        $this->assertStringStartsWith('BEGIN', $this->db->queries[0]['sql']);
        $this->assertStringStartsWith('COMMIT', $this->db->getLastSql());
    }
}