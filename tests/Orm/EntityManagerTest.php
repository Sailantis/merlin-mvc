<?php

namespace Azera\Tests\Orm;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Db/TestDatabase.php';
require_once __DIR__ . '/Fixtures/Article.php';
require_once __DIR__ . '/Fixtures/Relations.php';
require_once __DIR__ . '/FakeMongoCollection.php';

use Azera\AppContext;
use Azera\Db\DatabaseManager;
use Azera\Db\Database;
use Azera\Orm\EntityManager;
use Azera\Orm\FastHydrator;
use Azera\Orm\Heap;
use Azera\Orm\Metadata;
use Azera\Orm\Node;
use Azera\Orm\Storage\MongoStore;
use Azera\Orm\Storage\PdoStore;
use Azera\Orm\Storage\StoreManager;
use Azera\Tests\Db\TestDatabase;
use Azera\Tests\Orm\Fixtures\Article;
use Azera\Tests\Orm\Fixtures\Author;
use Azera\Tests\Orm\Fixtures\Comment;
use PHPUnit\Framework\TestCase;

/** Document base-class fixture: exercises the Document facade (not Model). */
#[\Azera\Orm\Attribute\Document(collection: 'docs')]
class DocumentFixture extends \Azera\Orm\Document
{
    public $_id;
    public $title;
    public $tags;
}

class EntityManagerTest extends TestCase
{
    private TestDatabase $db;
    private AppContext $ctx;
    private EntityManager $em;
    protected function setUp(): void
    {
        $this->db  = new TestDatabase('pgsql');
        $this->ctx = new AppContext();
        AppContext::setInstance($this->ctx);
        FastHydrator::clear();
        Metadata::clear();

        // Wire the default DatabaseManager role to the test DB so PdoStore
        // borrows it (same pattern as the app bootstrap).
        $dbm = new DatabaseManager();
        $dbm->set('default', $this->db);
        $dbm->set('read', $this->db);
        $dbm->set('write', $this->db);
        $this->ctx->set(DatabaseManager::class, $dbm);

        $stores = new StoreManager();
        $stores->set('sql', 'default', fn() => new PdoStore($dbm, 'read', 'write'));
        $stores->setDefault('sql', 'default');
        $this->ctx->set(StoreManager::class, $stores);

        $this->em = $this->ctx->entityManager();
    }

    protected function tearDown(): void
    {
        AppContext::reset();
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

    private function articleRow(int $id, string $title = 'T'): array
    {
        return ['id' => (string) $id, 'title' => $title, 'created_at' => null, 'status_code' => '0'];
    }

    private function lastDataSql(): ?string
    {
        $q = $this->dataQueries();
        return $q === [] ? null : end($q)['sql'];
    }

    /* ================================================== upsert pipeline
     *
     * SCHEDULED_UPSERT: one atomic INSERT ... ON CONFLICT DO UPDATE — the
     * DATABASE resolves insert-vs-update (no SELECT, no unique-violation
     * race). PK = conflict target; DO UPDATE SET covers NON-PK columns
     * only (the fast excluded-refs shape). */

    /**
     * Full-payload upsert (PK + all non-PK set): one statement, ON CONFLICT
     * target = "id", SET writes non-PK columns via EXCLUDED refs. All
     * columns present → nothing to backfill → no RETURNING (the fastest
     * shape).
     */
    public function testUpsertEmitsOnConflictWithNonPkExcludedSet(): void
    {
        $a = new Article();
        $a->id         = 999999;
        $a->title      = 'Sentinel';
        $a->status     = 1;
        $a->created_at = '2026-09-05 00:00:00';

        $this->em->upsert($a);
        $this->em->flush();

        $sql = $this->lastDataSql();
        $this->assertStringStartsWith('INSERT INTO "article"', $sql);
        $this->assertStringContainsString('ON CONFLICT ("id") DO UPDATE SET', $sql);
        $this->assertStringContainsString('"title" = EXCLUDED."title"', $sql);
        $this->assertStringContainsString('"status_code" = EXCLUDED."status_code"', $sql);
        $this->assertStringNotContainsString('"id" = EXCLUDED."id"', $sql); // PK never in SET
        $this->assertStringNotContainsString('RETURNING', $sql);
        $this->assertSame(Node::MANAGED, $this->heapNode($a)->state);
    }

    /**
     * Unset non-PK columns: RETURNING * refreshes DB defaults onto the
     * entity (mirrors insertOne's returning_all strategy).
     */
    public function testUpsertMissingColumnsUsesReturningAllAndBackfills(): void
    {
        $a = new Article();
        $a->id    = 999999;
        $a->title = 'Sentinel';
        // created_at / status unset → DB defaults must round-trip.
        $this->db->setMockResults([[['id' => '999999', 'title' => 'Sentinel', 'created_at' => '2026-09-05 00:00:00', 'status_code' => '0']]]);

        $this->em->upsert($a);
        $this->em->flush();

        $this->assertStringContainsString('RETURNING *', $this->lastDataSql());
        $this->assertSame('2026-09-05 00:00:00', $a->created_at);
        $this->assertSame(Node::MANAGED, $this->heapNode($a)->state);
    }

    /**
     * The upsert statement joins the flush transaction like every other
     * scheduled write (BEGIN ... COMMIT wrap).
     */
    public function testUpsertJoinsFlushTransaction(): void
    {
        $a = new Article();
        $a->id    = 999999;
        $a->title = 'Sentinel';

        $this->em->upsert($a);
        $this->em->flush();

        $this->assertStringStartsWith('BEGIN', $this->db->queries[0]['sql']);
        $this->assertStringStartsWith('COMMIT', $this->db->getLastSql());
    }

    /**
     * Upsert marks the entity MANAGED in the identity map: a follow-up
     * mutate + save() emits a diff UPDATE, not a second upsert.
     */
    public function testUpsertedEntityBecomesManagedAndSavesAsUpdate(): void
    {
        $a = new Article();
        $a->id    = 999999;
        $a->title = 'Sentinel';

        $this->em->upsert($a);
        $this->em->flush();
        $this->db->clearQueries();

        $a->title = 'Mutated';
        $this->assertTrue($a->save());

        $q = $this->dataQueries();
        $this->assertCount(1, $q);
        $this->assertStringStartsWith('UPDATE "article"', $q[0]['sql']);
        $this->assertStringNotContainsString('ON CONFLICT', $q[0]['sql']);
    }

    /**
     * Mongo routing: upsert on a document hits the mongo store's
     * updateOne(filter, $set, upsert:true) — never SQL.
     */
    public function testUpsertOnDocumentRoutesThroughMongoStore(): void
    {
        $fakes = new FakeMongoFactory();
        $this->ctx->get(StoreManager::class)->set('mongo', 'default', new MongoStore(fn($name) => $fakes->for($name)));

        $doc = new \Azera\Tests\Orm\Fixtures\ArticleDocument();
        $doc->_id   = 'abc123';
        $doc->title = 'Doc One';

        $this->em->upsert($doc);
        $this->em->flush();

        $articles = $fakes->for('articles');
        $this->assertCount(1, $articles->calls);
        $this->assertSame('update', $articles->calls[0]['op']);
        [$filter, $update, $options] = $articles->calls[0]['args'];
        $this->assertSame(['_id' => 'abc123'], $filter);
        $this->assertSame(['title' => 'Doc One'], $update['$set']); // PK excluded from $set
        $this->assertTrue($options['upsert']);
        $this->assertSame('Doc One', $articles->docs[0]['title']);
        $this->assertSame(Node::MANAGED, $this->heapNode($doc)->state);
    }

    private function heapNode(object $entity): Node
    {
        $node = $this->em->heap()->find($entity);
        $this->assertNotNull($node);
        return $node;
    }

    /**
     * PK set at insert time = nothing to backfill → plain INSERT, no
     * RETURNING clause (the per-backend strategy matrix in PdoStore).
     */
    public function testInsertWithPkSetUsesPlainInsertWithoutReturning(): void
    {
        $a = new Article();
        $a->id    = 42;
        $a->title = 'Sentinel';

        $this->em->persist($a);
        $this->em->flush();

        $sql = $this->lastDataSql();
        $this->assertStringStartsWith('INSERT INTO "article"', $sql);
        $this->assertStringNotContainsString('RETURNING', $sql);
    }

    /**
     * Auto-increment (PK omitted): smallest-payload INSERT + RETURNING "id",
     * and the generated id is backfilled onto the entity.
     */
    public function testInsertAutoIncrementUsesReturningIdAndBackfills(): void
    {
        $a = new Article();
        $a->title      = 'Auto';
        $a->created_at = '2026-09-03 10:00:00';
        $a->status     = 1;
        // all non-PK columns set, only id missing -> smallest payload
        $this->db->setMockResults([[['id' => 5]]]);

        $this->em->persist($a);
        $this->em->flush();

        $this->assertStringContainsString('RETURNING "id"', $this->lastDataSql());
        $this->assertSame(5, $a->id);
    }

    /**
     * Some non-PK columns left unset: fallback strategy RETURNING * so
     * DB defaults land back on the entity.
     */
    public function testInsertMissingColumnsFallsBackToReturningAll(): void
    {
        $a = new Article();
        $a->title = 'Auto';
        $this->db->setMockResults([[['id' => 5, 'title' => 'Auto', 'status_code' => 0]]]);

        $this->em->persist($a);
        $this->em->flush();

        $this->assertStringContainsString('RETURNING *', $this->lastDataSql());
    }

    /**
     * BelongsTo owner flushes before dependent: auto-generated owner PKs
     * can backfill into dependents' FK values (topological order).
     */
    public function testBelongsToOwnerFlushesBeforeDependent(): void
    {
        $this->db->setMockResults([
            [['id' => 9]], // owner insert RETURNING
            [['id' => 9]]  // (unused second)
        ]);

        $author = new Author();
        $author->name = 'X';

        $c = new Comment();
        $c->author_id = null;
        $c->body      = 'B';

        $this->em->persist($author);
        $this->em->persist($c);
        $this->em->flush();

        $q = $this->dataQueries();
        $this->assertCount(2, $q);
        $this->assertStringStartsWith('INSERT INTO "author"', $q[0]['sql']);
        $this->assertStringStartsWith('INSERT INTO "comment"', $q[1]['sql']);
    }

    public function testPersistFlushInsertsThroughStore(): void
    {
        $a = new Article();
        $a->id    = 42;
        $a->title = 'Sentinel';

        $this->em->persist($a);
        $this->em->flush();

        $q = $this->dataQueries();
        $this->assertNotEmpty($q);
        $this->assertStringStartsWith('INSERT INTO "article"', $q[0]['sql']);
    }

    public function testFindReturnsNullWhenStoreMisses(): void
    {
        $this->db->setMockResults([[]]); // findByPk -> no rows
        $this->assertNull($this->em->find(Article::class, ['id' => 1]));
    }
    public function testFindHydratesOntoHeapAndIsCached(): void
    {
        $row = $this->articleRow(7, 'Seven');
        $this->db->setMockResults([[$row]]);

        $a = $this->em->find(Article::class, ['id' => 7]);
        $this->assertNotNull($a);
        $this->assertSame('Seven', $a->title);

        // Second find: heap hit → NO additional SQL
        $before = count($this->db->queries);
        $again  = $this->em->find(Article::class, ['id' => 7]);
        $this->assertSame($a, $again);
        $this->assertCount($before, $this->db->queries);
    }

    public function testUpdateViaEmWritesOnlyChangedColumns(): void
    {
        // 1) seed load
        $this->db->setMockResults([[$this->articleRow(1, 'One')]]);
        $a = $this->em->find(Article::class, ['id' => 1]);
        $this->assertNotNull($a);
        $this->db->clearQueries();

        $a->title = 'Two';
        $this->em->persist($a);
        $this->em->flush();

        $q = $this->dataQueries();
        $this->assertCount(1, $q);
        $this->assertStringStartsWith('UPDATE "article"', $q[0]['sql']);
        $this->assertStringContainsString('SET "title" = ?', $q[0]['sql']);
        $this->assertStringNotContainsString('status_code', $q[0]['sql']);
    }

    public function testCleanEntityIsNotWritten(): void
    {
        $this->db->setMockResults([[$this->articleRow(1, 'Same')]]);
        $a = $this->em->find(Article::class, ['id' => 1]);
        $this->assertNotNull($a);
        $this->db->clearQueries();

        $this->em->persist($a);
        $this->em->flush();

        $this->assertSame([], $this->db->queries);
    }

    public function testRemoveDetachesAndDeletes(): void
    {
        $this->db->setMockResults([[$this->articleRow(1, 'Doomed')]]);
        $a = $this->em->find(Article::class, ['id' => 1]);
        $this->assertNotNull($a);
        $this->db->clearQueries();

        $this->em->remove($a);
        $this->em->flush();

        $q = $this->dataQueries();
        $this->assertCount(1, $q);
        $this->assertStringStartsWith('DELETE FROM "article"', $q[0]['sql']);
        $this->assertStringContainsString('WHERE "id" = ?', $q[0]['sql']);
        $this->assertNull($this->em->heap()->findById(Article::class, ['id' => 1]));
        $this->assertNull($this->em->find(Article::class, ['id' => 1])); // detached — but wait: find re-queries
    }

    public function testClearWipesIdentityAndScheduledWork(): void
    {
        $a = new Article();
        $a->id    = 5;
        $a->title = 'Pending';

        $this->em->persist($a);
        $this->em->clear();

        // Scheduled work is forgotten: flush is a no-op.
        $before = count($this->db->queries);
        $this->em->flush();
        $this->assertCount($before, $this->db->queries);
        $this->assertSame(0, $this->em->heap()->count());
    }

    public function testResetStateClearsHeapAndUow(): void
    {
        $a = new Article();
        $a->id    = 5;
        $a->title = 'Pending';

        $this->em->persist($a);
        $this->em->resetState();

        $this->assertSame(0, $this->em->heap()->count());
    }

    public function testFindSharesHeapWithQueryEntities(): void
    {
        $row = $this->articleRow(9, 'Shared');

        // EM find first (one mock result consumed by findByPk)
        $this->db->setMockResults([[$row]]);
        $viaEm = $this->em->find(Article::class, ['id' => 9]);
        $this->assertNotNull($viaEm);

        // Query entities() on the SAME AppContext heap — identity hit
        // must return the identical object without re-hydrating.
        $this->db->clearQueries();
        $this->db->setMockResults([[$row]]);

        $viaQuery = \Azera\Db\Query::modelFor(Article::class, $this->db)
            ->where('id', '=', 9)
            ->firstEntity();

        $this->assertNotNull($viaQuery);
        $this->assertSame($viaEm, $viaQuery);
    }

    public function testTransactionWrapsFlushViaStore(): void
    {
        $a = new Article();
        $a->id = 3;
        $this->em->persist($a);
        $this->em->flush();

        $this->assertStringStartsWith('BEGIN', $this->db->queries[0]['sql']);
        $this->assertStringStartsWith('COMMIT', $this->db->getLastSql());
    }

    /* ================================================== facade tests
     *
     * Model::save()/delete() and Document::save()/delete() must land in
     * the SAME EM pipeline as EM-direct calls. Facade models are Model
     * subclasses (DirtyState + EM heap-diff engine applies), so these
     * tests exercise the full pipeline: legacy loads → adopt → flush →
     * identical SQL.
     */

    /**
     * Hydrated-then-mutated facade model: save() must produce EXACTLY one
     * UPDATE of the changed column — the heap-diff parity proof (identical
     * SQL to an EM-direct find → mutate → persist → flush).
     */
    public function testModelSaveAfterLoadWritesOnlyChangedColumns(): void
    {
        $row = $this->articleRow(7, 'Seven');
        $this->db->setMockResults([[$row]]);

        $article = Article::find(7);
        $this->assertNotNull($article);
        $this->db->clearQueries();

        $article->title = 'Mutated';
        $this->assertTrue($article->save());

        $q = $this->dataQueries();
        $this->assertCount(1, $q);
        $this->assertStringStartsWith('UPDATE "article"', $q[0]['sql']);
        $this->assertStringContainsString('SET "title" = ?', $q[0]['sql']);
        $this->assertStringNotContainsString('status_code', $q[0]['sql']);
        $this->assertStringContainsString('WHERE "id" = ?', $q[0]['sql']);
        $this->assertFalse($article->hasChanged());
    }

    /**
     * Same load, no mutation: save() must write NOTHING (clean-entity
     * parity with the EM path).
     */
    public function testModelCleanSaveEmitsNoSql(): void
    {
        $row = $this->articleRow(7, 'Seven');
        $this->db->setMockResults([[$row]]);

        $article = Article::find(7);
        $this->db->clearQueries();

        $this->assertFalse($article->save());
        $this->assertSame([], $this->db->queries);
    }

    /**
     * ID'd model built manually (no store load): legacy blind-UPDATE
     * parity — one UPDATE writing every set non-PK column.
     */
    public function testModelSaveOnManualIdEntityWritesAllSetColumns(): void
    {
        $a = new Article();
        $a->id    = 42;
        $a->title = 'Manual';

        $this->assertTrue($a->save());

        $q = $this->dataQueries();
        $this->assertCount(1, $q);
        $this->assertStringStartsWith('UPDATE "article"', $q[0]['sql']);
        $this->assertStringContainsString('SET "title" = ?', $q[0]['sql']);
        $this->assertStringNotContainsString('status_code', $q[0]['sql']);
    }

    /**
     * PK-less model: INSERT + auto-increment PK backfill.
     */
    public function testModelSaveInsertsAndBackfillsGeneratedPk(): void
    {
        $a = new Article();
        $a->title      = 'Auto';
        $a->created_at = '2026-09-03 10:00:00';
        $a->status     = 1;
        $this->db->setMockResults([[['id' => 5]]]);

        $this->assertTrue($a->save());
        $this->assertSame(5, $a->id);
        $this->assertStringContainsString('RETURNING "id"', $this->lastDataSql());
    }

    /**
     * Identity: find() hydrates ON the EM pipeline (heap-tracked) — the
     * facade save() needs no adoption on this path, and the EM find of
     * the same row resolves to the SAME object.
     */
    public function testModelFacadeAdoptsIntoSharedHeap(): void
    {
        $row = $this->articleRow(7, 'Seven');
        $this->db->setMockResults([[$row]]);

        $article = Article::find(7);
        $this->assertNotNull($article);
        $this->assertTrue($this->em->contains($article)); // identity-mapped read

        $article->title = 'Adopted';
        $this->assertTrue($article->save()); // heap-diff flush (no adopt needed)
        $this->assertTrue($this->em->contains($article));
        $this->assertSame($article, $this->em->find(Article::class, ['id' => 7]));
    }

    /**
     * Facade delete: adopt + remove + flush → one DELETE by PK.
     */
    public function testModelDeleteEmitsSinglePkDelete(): void
    {
        $row = $this->articleRow(7, 'Doomed');
        $this->db->setMockResults([[$row]]);

        $article = Article::find(7);
        $this->db->clearQueries();

        $this->assertTrue($article->delete());

        $q = $this->dataQueries();
        $this->assertCount(1, $q);
        $this->assertStringStartsWith('DELETE FROM "article"', $q[0]['sql']);
        $this->assertStringContainsString('WHERE "id" = ?', $q[0]['sql']);
        $this->assertFalse($this->em->contains($article));
    }

    /**
     * Untracked document (Model facade over mongo metadata): save() →
     * INSERT through the EM, routed to the MONGO store (never the SQL
     * store's article_document table). Arrays pass through raw — BSON
     * owns the encoding (no JSON-encode at the EM layer).
     */
    public function testModelFacadeOverMongoMetadataInsertRoutesThroughEm(): void
    {
        $fakes = new FakeMongoFactory();
        $this->ctx->get(StoreManager::class)->set('mongo', 'default', new MongoStore(fn($name) => $fakes->for($name)));

        $doc = new \Azera\Tests\Orm\Fixtures\ArticleDocument();
        $doc->_id   = null;
        $doc->title = 'Doc One';
        $doc->tags  = ['a', 'b'];

        $this->assertTrue($doc->save());
        $this->assertSame('1', $doc->_id); // driver-generated id backfilled

        $articles = $fakes->for('articles'); // #[Document(collection)]
        $this->assertCount(1, $articles->docs);
        $this->assertSame(['a', 'b'], $articles->docs[0]['tags']); // RAW array, not JSON
        $this->assertSame('Doc One', $articles->docs[0]['title']);
    }

    /**
     * Tracked, unchanged document: save() → false, no mongo ops.
     */
    public function testDocumentFacadeCleanSaveIsNoOp(): void
    {
        $fakes = new FakeMongoFactory();
        $this->ctx->get(StoreManager::class)->set('mongo', 'default', new MongoStore(fn($name) => $fakes->for($name)));
        $fakes->for('articles')->docs[] = ['_id' => 'abc123', 'title' => 'Doc One', 'tags' => null];

        $doc = $this->em->find(\Azera\Tests\Orm\Fixtures\ArticleDocument::class, ['_id' => 'abc123']);
        $this->assertNotNull($doc);

        $this->assertFalse($doc->save());
        $this->assertCount(1, $fakes->for('articles')->calls); // only the seed find
    }

    /**
     * Tracked, mutated document: save() → diff UPDATE ($set) through the
     * EM — only changed fields, PK excluded from $set.
     */
    public function testDocumentFacadeUpdateWritesOnlyChangedFields(): void
    {
        $fakes = new FakeMongoFactory();
        $this->ctx->get(StoreManager::class)->set('mongo', 'default', new MongoStore(fn($name) => $fakes->for($name)));
        $fakes->for('articles')->docs[] = ['_id' => 'abc123', 'title' => 'Doc One', 'tags' => null];

        $doc = $this->em->find(\Azera\Tests\Orm\Fixtures\ArticleDocument::class, ['_id' => 'abc123']);
        $this->assertNotNull($doc);

        $doc->title = 'Doc Two';
        $this->assertTrue($doc->save());

        $articles = $fakes->for('articles');
        $update   = null;
        foreach ($articles->calls as $call) {
            if ($call['op'] === 'update') {
                $update = $call;
            }
        }
        $this->assertNotNull($update);
        $this->assertSame(['title' => 'Doc Two'], $update['args'][1]['$set']);
        $this->assertSame('Doc Two', $articles->docs[0]['title']);
    }

    /**
     * Document BASE-CLASS facade (extends Document, not Model): delete()
     * routes remove + flush through the EM — one mongo deleteOne by _id.
     */
    public function testDocumentBaseFacadeDeleteRoutesThroughEm(): void
    {
        $fakes = new FakeMongoFactory();
        $this->ctx->get(StoreManager::class)->set('mongo', 'default', new MongoStore(fn($name) => $fakes->for($name)));
        $fakes->for('docs')->docs[] = ['_id' => 'abc123', 'title' => 'Doc One', 'tags' => null];

        $doc = $this->em->find(DocumentFixture::class, ['_id' => 'abc123']);
        $this->assertNotNull($doc);

        $this->assertTrue($doc->delete());

        $this->assertSame([], $fakes->for('docs')->docs); // deleted
        $this->assertFalse($this->em->contains($doc));
    }
}