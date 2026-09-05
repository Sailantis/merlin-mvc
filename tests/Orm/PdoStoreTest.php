<?php

namespace Azera\Tests\Orm;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Db/TestDatabase.php';
require_once __DIR__ . '/Fixtures/Article.php';

use Azera\AppContext;
use Azera\Orm\Metadata;
use Azera\Orm\Storage\PdoStore;
use Azera\Orm\Storage\StoreManager;
use Azera\Tests\Db\TestDatabase;
use Azera\Tests\Orm\Fixtures\Article;
use Azera\Tests\Orm\Fixtures\InventoryItem;
use PHPUnit\Framework\TestCase;

class PdoStoreTest extends TestCase
{
    private TestDatabase $db;
    private PdoStore $store;

    protected function setUp(): void
    {
        AppContext::setInstance(new AppContext());
        Metadata::clear();
        $this->db = new TestDatabase('pgsql');
        AppContext::instance()->dbManager()->set('read', $this->db);
        AppContext::instance()->dbManager()->set('write', $this->db);
        $this->store = new PdoStore(AppContext::instance()->dbManager(), 'read', 'write');
    }

    public function testInsertPkSetPlainInsert(): void
    {
        $this->store->insertOne(Article::class, ['id' => 42, 'title' => 'T', 'created_at' => null, 'status_code' => null]);

        $q = $this->dataQueries();
        $this->assertCount(1, $q);
        $this->assertStringStartsWith('INSERT INTO "article"', $q[0]['sql']);
        $this->assertStringNotContainsString('RETURNING', $q[0]['sql']);
    }

    public function testInsertAutoIncrementReturningId(): void
    {
        $this->db->setMockResults([[['id' => 5]]]);
        $result = $this->store->insertOne(Article::class, ['id' => null, 'title' => 'T', 'created_at' => '2026-01-01', 'status_code' => 1]);

        $q = $this->dataQueries();
        $this->assertStringContainsString('RETURNING "id"', $q[0]['sql']);
        $this->assertSame(5, $result['id']);
    }

    public function testFindByReturnsRawRows(): void
    {
        $this->db->setMockResults([[['id' => 1, 'title' => 'A']]]);

        $rows = $this->store->findBy(Article::class, ['id' => 1]);

        $this->assertSame([['id' => 1, 'title' => 'A']], $rows);
        $this->assertStringStartsWith('SELECT * FROM "article"', $this->lastDataSql());
    }

    public function testSchemaQualifiedTableInAllSql(): void
    {
        // #[Table(schema: 'warehouse', name: 'inventory_items')] — every
        // statement targets "warehouse"."inventory_items".
        $this->db->setMockResults([[['cnt' => 3]]]);

        $this->store->count(InventoryItem::class, ['tenant_id' => 1]);

        $sql = $this->lastDataSql();
        $this->assertStringStartsWith(
            'SELECT COUNT(*) AS cnt FROM "warehouse"."inventory_items"',
            $sql
        );

        // INSERT / UPDATE / DELETE paths use the same qualification.
        $this->db->clearQueries();
        $this->store->insertOne(InventoryItem::class, ['tenant_id' => 1, 'item_id' => 2, 'name' => 'X']);
        $this->assertStringStartsWith(
            'INSERT INTO "warehouse"."inventory_items"',
            $this->dataQueries()[0]['sql']
        );

        $this->db->clearQueries();
        $this->store->updateOne(InventoryItem::class, ['name' => 'Y'], ['tenant_id' => 1, 'item_id' => 2]);
        $this->assertStringStartsWith(
            'UPDATE "warehouse"."inventory_items"',
            $this->dataQueries()[0]['sql']
        );

        $this->db->clearQueries();
        $this->store->deleteOne(InventoryItem::class, ['tenant_id' => 1, 'item_id' => 2]);
        $this->assertStringStartsWith(
            'DELETE FROM "warehouse"."inventory_items"',
            $this->dataQueries()[0]['sql']
        );
    }

    public function testCount(): void
    {
        $this->db->setMockResults([[['cnt' => 7]]]);

        $this->assertSame(7, $this->store->count(Article::class));
    }

    private function dataQueries(): array
    {
        return array_values(array_filter($this->db->queries, fn($q) => !in_array($q['sql'], ['BEGIN', 'COMMIT', 'ROLLBACK'], true)));
    }

    private function lastDataSql(): ?string
    {
        $q = $this->dataQueries();
        return $q === [] ? null : end($q)['sql'];
    }
}