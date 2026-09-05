<?php
namespace Azera\Tests\Mvc;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Db/TestDatabase.php';

use Azera\AppContext;
use Azera\Db\ModelMapping;
use Azera\Db\DatabaseManager;
use Azera\Orm\FastHydrator;
use Azera\Orm\Metadata;
use Azera\Orm\Storage\PdoStore;
use Azera\Orm\Storage\StoreManager;
use Azera\Tests\Db\TestPgDatabase;
use Azera\Tests\Db\TestMysqlDatabase;
use Azera\Tests\Db\TestSqliteDatabase;
use PHPUnit\Framework\TestCase;

class DummyModel extends \Azera\Orm\Model
{
    public $id;
    public $name;
    public $_internal;

    public function idFields(): array
    {
        return ['id'];
    }
}

class DummyDefaultedModel extends \Azera\Orm\Model
{
    public $id;
    public $name;
    public $created_at;

    public function idFields(): array
    {
        return ['id'];
    }
}

class DummyCompositeModel extends \Azera\Orm\Model
{
    public $tenant_id;
    public $id;
    public $name;

    public function idFields(): array
    {
        return ['tenant_id', 'id'];
    }
}

class ModelTest extends TestCase
{
    protected function setUp(): void
    {
        AppContext::setInstance(new AppContext());
        ModelMapping::usePluralTableNames(false);
        FastHydrator::clear();
        Metadata::clear();
        self::resetConnectionRoles();
    }

    protected function tearDown(): void
    {
        // The role maps are static on Model — reset so they never leak
        // into later test files.
        self::resetConnectionRoles();
    }

    /** Clear Model's static connection-role maps (protected statics). */
    private static function resetConnectionRoles(): void
    {
        foreach (['__defaultReadRoles', '__defaultWriteRoles'] as $prop) {
            $ref = new \ReflectionProperty(\Azera\Orm\Model::class, $prop);
            $ref->setValue(null, []);
        }
    }

    public function testStateSaveLoadAndHasChanged(): void
    {
        $db = new TestPgDatabase();
        $this->wireStore($db);
        $db->setMockResults([
            [['id' => '1', 'name' => 'Alice']]
        ]);

        // Load through the EM: the heap node snapshot is the baseline.
        $m = DummyModel::find(1);
        $this->assertNotNull($m);
        $this->assertFalse($m->hasChanged());

        $m->name = 'Bob';
        $this->assertTrue($m->hasChanged());
        $this->assertSame(['name' => 'Bob'], $m->changedData());

        $m->loadState();
        $this->assertEquals('Alice', $m->name);
        $this->assertFalse($m->hasChanged());
    }

    public function testCreatePopulatesIdAndUpdatesState(): void
    {
        $db = new TestPgDatabase();
        $this->wireStore($db);
        // RETURNING "id" echoes the generated PK back
        $db->setMockResults([[['id' => 123]]]);

        $m = new DummyModel();
        $m->name = 'Charlie';

        $this->assertTrue($m->save());
        $this->assertSame(123, $m->id);

        // Post-save contract: hasChanged() = false (heap node synced).
        $this->assertFalse($m->hasChanged());
        $this->assertEquals('Charlie', $m->name);
    }

    public function testSaveOnTrackedModelWritesOnlyChangedColumns(): void
    {
        $db = new TestPgDatabase();
        $this->wireStore($db);

        // Load through the EM (heap-tracked), then mutate one field.
        $db->setMockResults([
            [['id' => '5', 'name' => 'Delta']]
        ]);

        $m = DummyModel::find(5);
        $this->assertNotNull($m);
        $db->clearQueries();

        $m->name = 'Delta2';
        $this->assertTrue($m->save());

        // The facade save() flushes inside a transaction (BEGIN … COMMIT);
        // assert on the data statements, not the raw log tail.
        $dataQueries = array_values(array_filter(
            $db->queries,
            fn($q) => !in_array($q['sql'], ['BEGIN', 'COMMIT', 'ROLLBACK'], true)
        ));
        $query = end($dataQueries);
        $this->assertStringContainsString('UPDATE "dummy_model"', $query['sql']);
        $this->assertStringContainsString('SET "name" = ?', $query['sql']);
        $this->assertStringContainsString('WHERE "id" = ?', $query['sql']);
        $this->assertFalse($m->hasChanged(), 'State should be updated after save()');
    }

    public function testCleanSaveEmitsNoSql(): void
    {
        $db = new TestPgDatabase();
        $this->wireStore($db);

        $db->setMockResults([
            [['id' => '5', 'name' => 'Same']]
        ]);

        $m = DummyModel::find(5);
        $db->clearQueries();

        $this->assertFalse($m->save());
        $this->assertSame([], $db->queries);
    }

    public function testInsertOmitsNullIdAndDefaultColumns(): void
    {
        $db = new TestPgDatabase();
        $this->wireStore($db);
        // RETURNING * (missing columns) echoes the DB defaults back
        $db->setMockResults([
            [
                ['id' => 9, 'name' => 'Echo', 'created_at' => '2026-04-05 12:00:00']
            ]
        ]);

        $model = new DummyDefaultedModel();
        $model->name = 'Echo';

        $this->assertTrue($model->save());

        // RETURNING * statement + tx wrapper: filter to data statements.
        $dataQueries = array_values(array_filter(
            $db->queries,
            fn($q) => !in_array($q['sql'], ['BEGIN', 'COMMIT', 'ROLLBACK'], true)
        ));
        $query = end($dataQueries);
        $this->assertNotNull($query);
        $this->assertStringContainsString('INSERT INTO "dummy_defaulted_model"', $query['sql']);
        $this->assertStringContainsString('"name"', $query['sql']);
        $this->assertStringNotContainsString('"id"', $query['sql']);
        $this->assertStringNotContainsString('"created_at"', $query['sql']);
        // Values are BOUND parameters on the EM path (not inline-escaped).
        $this->assertContains('Echo', array_values($query['params']));
        $this->assertSame(9, $model->id);
        $this->assertSame('2026-04-05 12:00:00', $model->created_at);
    }

    public function testSaveForNewModelDoesNotUsePrimaryKeyUpsert(): void
    {
        $db = new TestPgDatabase();
        $this->wireStore($db);
        $db->setMockResults([
            [
                ['id' => 15, 'name' => 'Golf']
            ]
        ]);

        $model = new DummyModel();
        $model->name = 'Golf';

        $this->assertTrue($model->save());

        // The facade save() flushes inside a transaction (BEGIN … COMMIT);
        // assert on the data statements, not the raw log tail.
        $dataQueries = array_values(array_filter(
            $db->queries,
            fn($q) => !in_array($q['sql'], ['BEGIN', 'COMMIT', 'ROLLBACK'], true)
        ));
        $this->assertNotEmpty($dataQueries);
        $query = end($dataQueries);
        $this->assertStringContainsString('INSERT INTO "dummy_model"', $query['sql']);
        $this->assertStringNotContainsString('ON CONFLICT', strtoupper($query['sql']));
        $this->assertSame(15, $model->id);
    }

    /**
     * Model::upsert() routes through the EM: one atomic
     * INSERT ... ON CONFLICT ("id") DO UPDATE SET "name"=EXCLUDED."name"
     * — PK present in VALUES but never in SET (the fast shape), entity
     * MANAGED afterwards (identity-mapped, later save() = diff UPDATE).
     */
    public function testUpsertGoesThroughEmPipeline(): void
    {
        $db = new TestPgDatabase();
        $this->wireStore($db);

        $model = DummyModel::upsert(['id' => 999999, 'name' => 'Sentinel']);
        $this->assertSame($model, DummyModel::find(999999)); // identity-mapped

        $dataQueries = array_values(array_filter(
            $db->queries,
            fn($q) => !in_array($q['sql'], ['BEGIN', 'COMMIT', 'ROLLBACK'], true)
        ));
        $this->assertCount(1, $dataQueries);
        $sql = $dataQueries[0]['sql'];
        $this->assertStringContainsString('INSERT INTO "dummy_model"', $sql);
        $this->assertStringContainsString('ON CONFLICT ("id") DO UPDATE SET', $sql);
        $this->assertStringContainsString('"name" = EXCLUDED."name"', $sql);
        $this->assertStringNotContainsString('"id" = EXCLUDED."id"', $sql);

        // Follow-up save() after mutation = plain diff UPDATE.
        $db->clearQueries();
        $model->name = 'Mutated';
        $this->assertTrue($model->save());
        $saveQueries = array_values(array_filter(
            $db->queries,
            fn($q) => !in_array($q['sql'], ['BEGIN', 'COMMIT', 'ROLLBACK'], true)
        ));
        $this->assertCount(1, $saveQueries);
        $this->assertStringStartsWith('UPDATE "dummy_model"', $saveQueries[0]['sql']);
    }

    public function testWriteClosesReturningCursor(): void
    {
        $db = new TestPgDatabase();
        $this->wireStore($db);
        $db->setMockResults([
            [
                ['id' => 21, 'name' => 'Hotel']
            ]
        ]);

        $model = new DummyModel();
        $model->name = 'Hotel';
        $this->assertTrue($model->save());
        $this->assertSame(21, $model->id);

        // The RETURNING statement's cursor must be closed after the write so
        // that it does not hold a lock on the connection (SQLite WAL).
        $lastStmt = $db->getLastPdoStatement();
        $this->assertNotNull($lastStmt);
        $this->assertTrue($lastStmt->cursorClosed, 'RETURNING cursor should be closed after a write');
    }

    public function testRefreshOnWriteResultSetThrows(): void
    {
        $db = new TestPgDatabase();

        // A write statement (isReadResultSet=false) produces a ResultSet, but
        // it must not be refreshable — refresh() would re-execute the write.
        $rs = new \Azera\Db\ResultSet(
            $db,
            $db->query('INSERT INTO "dummy_model" ("name") VALUES (\'India\') RETURNING *'),
            'INSERT INTO "dummy_model" ("name") VALUES (\'India\') RETURNING *',
            [],
            false
        );

        $this->expectException(\Azera\Db\Exception::class);
        $rs->refresh();
    }

    public function testRefreshOnReadResultSetSucceeds(): void
    {
        $db = new TestPgDatabase();

        $rs = new \Azera\Db\ResultSet(
            $db,
            $db->query('SELECT * FROM "dummy_model"'),
            'SELECT * FROM "dummy_model"',
            [],
            true
        );

        $rs->refresh();
        $this->assertTrue(true, 'Refresh of a read-only result set should not throw');
    }

    public function testFindHydratesModelInstance(): void
    {
        $db = new TestPgDatabase();
        $this->wireStore($db);
        $db->setMockResults([
            [
                ['id' => 5, 'name' => 'Foxtrot']
            ]
        ]);

        $model = DummyModel::find(5);

        $this->assertInstanceOf(DummyModel::class, $model);
        $this->assertSame(5, $model->id);
        $this->assertSame('Foxtrot', $model->name);
        // Loaded onto the heap: identity-mapped + dirty-state baseline ready.
        $this->assertFalse($model->hasChanged());
    }

    public function testFindIsIdentityMappedAcrossCalls(): void
    {
        $db = new TestPgDatabase();
        $this->wireStore($db);
        $db->setMockResults([
            [['id' => 5, 'name' => 'Foxtrot']],
            [['id' => 5, 'name' => 'Foxtrot']]
        ]);

        $first  = DummyModel::find(5);
        $second = DummyModel::find(5);

        $this->assertSame($first, $second, 'EM identity map must return the same instance');
    }

    public function testCompositeKeyInsertBackfillsAllIdsViaReturningOnMysql(): void
    {
        $db = new TestMysqlDatabase();
        $this->wireStore($db);
        $db->setMockResults([
            [
                ['tenant_id' => 7, 'id' => 42, 'name' => 'Composite']
            ]
        ]);

        $model = new DummyCompositeModel();
        $model->name = 'Composite';

        $this->assertTrue($model->save());

        $dataQueries = array_values(array_filter(
            $db->queries,
            fn($q) => !in_array($q['sql'], ['BEGIN', 'COMMIT', 'ROLLBACK'], true)
        ));
        $query = end($dataQueries);
        $this->assertNotNull($query);
        $this->assertStringContainsString('RETURNING', strtoupper($query['sql']));
        $this->assertSame(7, $model->tenant_id);
        $this->assertSame(42, $model->id);
    }

    public function testCompositeKeyInsertBackfillsAllIdsViaReturningOnSqlite(): void
    {
        $db = new TestSqliteDatabase();
        $this->wireStore($db);
        $db->setMockResults([
            [
                ['tenant_id' => 3, 'id' => 99, 'name' => 'Composite']
            ]
        ]);

        $model = new DummyCompositeModel();
        $model->name = 'Composite';

        $this->assertTrue($model->save());

        $dataQueries = array_values(array_filter(
            $db->queries,
            fn($q) => !in_array($q['sql'], ['BEGIN', 'COMMIT', 'ROLLBACK'], true)
        ));
        $query = end($dataQueries);
        $this->assertNotNull($query);
        $this->assertStringContainsString('RETURNING', strtoupper($query['sql']));
        $this->assertSame(3, $model->tenant_id);
        $this->assertSame(99, $model->id);
    }

    public function testCompositeKeyFindAcceptsOrderedAndAssocIdArrays(): void
    {
        $db = new TestPgDatabase();
        $this->wireStore($db);
        $db->setMockResults([
            [['tenant_id' => 3, 'id' => 7, 'name' => 'Row']]
        ]);

        $model = DummyCompositeModel::find([3, 7]);
        $this->assertInstanceOf(DummyCompositeModel::class, $model);

        $sql = $db->getLastQuery()['sql'];
        $this->assertStringContainsString('"tenant_id" = ?', $sql);
        $this->assertStringContainsString('"id" = ?', $sql);
    }

    public function testCompositeKeyScalarIdThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        DummyCompositeModel::find(7);
    }

    public function testConnectionAttributePrecedence(): void
    {
        Metadata::clear();

        // Fixture class with #[Connection(read: 'replica', write: 'primary')].
        $m = new \Azera\Tests\Orm\Fixtures\InventoryItem();
        $this->assertSame('replica', $m->readRole());
        $this->assertSame('primary', $m->writeRole());

        // Runtime setter on the CONCRETE class beats the attribute (dynamic
        // > static) — the per-request tenancy escape hatch.
        \Azera\Tests\Orm\Fixtures\InventoryItem::setDefaultReadRole('runtime-role');
        $this->assertSame('runtime-role', $m->readRole());

        // Base-model global override does NOT beat the attribute — the
        // attribute sits ABOVE the base fallback in precedence.
        \Azera\Orm\Model::setDefaultReadRole('global');
        $this->assertSame('runtime-role', $m->readRole());
    }

    public function testConnectionRoleFallbackChain(): void
    {
        Metadata::clear();

        // No attribute, no runtime override → type-name fallback.
        $m = new DummyModel();
        $this->assertSame('read', $m->readRole());
        $this->assertSame('write', $m->writeRole());

        // Base-model global override applies when nothing else is set.
        \Azera\Orm\Model::setDefaultRole('shared');
        $this->assertSame('shared', $m->readRole());
        $this->assertSame('shared', $m->writeRole());
    }

    public function testMetadataBackedSourceSchemaIdFields(): void
    {
        Metadata::clear();

        // #[Table(name: 'inventory_items', schema: 'warehouse')] +
        // #[Column(pk: true)] composite key — all three accessors resolve
        // from compiled metadata with no method overrides on the class.
        $m = new \Azera\Tests\Orm\Fixtures\InventoryItem();
        $this->assertSame('inventory_items', $m->source());
        $this->assertSame('warehouse', $m->schema());
        $this->assertSame(['tenant_id', 'item_id'], $m->idFields());

        // Convention fallback unchanged for attribute-less models.
        $plain = new DummyModel();
        $this->assertSame('dummy_model', $plain->source());
        $this->assertNull($plain->schema());
        $this->assertSame(['id'], $plain->idFields());
    }

    /**
     * Wire the EM's Store seam to the given test DB (same pattern as the
     * EntityManagerTest bootstrap): StoreManager 'default' role over a
     * PdoStore borrowing the DatabaseManager's default/read/write roles.
     */
    private function wireStore(TestPgDatabase|TestMysqlDatabase|TestSqliteDatabase $db): void
    {
        $dbm = new DatabaseManager();
        $dbm->set('default', $db);
        $dbm->set('read', $db);
        $dbm->set('write', $db);
        AppContext::instance()->set(DatabaseManager::class, $dbm);

        $stores = new StoreManager();
        $stores->set('sql', 'default', fn() => new PdoStore($dbm, 'read', 'write'));
        $stores->setDefault('sql', 'default');
        AppContext::instance()->set(StoreManager::class, $stores);
    }
}