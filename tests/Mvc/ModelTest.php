<?php
namespace Azera\Tests\Mvc;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Db/TestDatabase.php';

use Azera\AppContext;
use Azera\Db\Query;
use Azera\Core\ModelMapping;
use Azera\Tests\Db\TestPgDatabase;
use Azera\Tests\Db\TestMysqlDatabase;
use Azera\Tests\Db\TestSqliteDatabase;
use PHPUnit\Framework\TestCase;

class DummyModel extends \Azera\Core\Model
{
    public $id;
    public $name;
    public $_internal;

    public function idFields(): array
    {
        return ['id'];
    }
}

class DummyDefaultedModel extends \Azera\Core\Model
{
    public $id;
    public $name;
    public $created_at;

    public function idFields(): array
    {
        return ['id'];
    }
}

class DummyCompositeModel extends \Azera\Core\Model
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
    }

    public function testStateSaveLoadAndHasChanged(): void
    {
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);

        $m = new DummyModel();
        $m->id        = null;
        $m->name      = 'Alice';
        $m->_internal = 'secret';

        $m->saveState();
        $this->assertFalse($m->hasChanged());

        $m->name = 'Bob';
        $this->assertTrue($m->hasChanged());

        $m->loadState();
        $this->assertEquals('Alice', $m->name);
        $this->assertFalse($m->hasChanged());
    }

    public function testCreatePopulatesIdAndUpdatesState(): void
    {
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);
        // Simulate DB returning the inserted row
        $db->setMockResults([
            [
                ['id' => 123, 'name' => 'Charlie']
            ]
        ]);

        $m = new DummyModel();
        $m->name = 'Charlie';

        $this->assertTrue($m->insert());
        $this->assertEquals(123, $m->id);

        $state = $m->getState();
        $this->assertNotNull($state);
        $this->assertEquals(123, $state->id);
        $this->assertEquals('Charlie', $state->name);
    }

    public function testUpdateExecutesAndClearsChanges(): void
    {
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);
        // Create the model first so a state exists, then change and update
        $db->setMockResults([
            [
                ['id' => 5, 'name' => 'Delta']
            ]
        ]);

        $m = new DummyModel();
        $m->name = 'Delta';
        $this->assertTrue($m->insert());

        $m->name = 'Delta2';
        $db->clearQueries();

        $result = $m->update();
        $this->assertTrue($result);
        $this->assertNotEmpty($db->queries, 'Update should execute queries on the driver');
        $this->assertFalse($m->hasChanged(), 'State should be updated after update()');
    }

    public function testInsertOmitsNullIdAndDefaultColumns(): void
    {
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);
        $db->setMockResults([
            [
                ['id' => 9, 'name' => 'Echo', 'created_at' => '2026-04-05 12:00:00']
            ]
        ]);

        $model = new DummyDefaultedModel();
        $model->id         = null;
        $model->name       = 'Echo';
        $model->created_at = null;

        $this->assertTrue($model->insert());

        $query = $db->getLastQuery();
        $this->assertNotNull($query);
        $this->assertStringContainsString('INSERT INTO "dummy_defaulted_model"', $query['sql']);
        $this->assertStringContainsString('"name"', $query['sql']);
        $this->assertStringNotContainsString('"id"', $query['sql']);
        $this->assertStringNotContainsString('"created_at"', $query['sql']);
        $this->assertStringContainsString("'Echo'", $query['sql']);
        $this->assertSame(9, $model->id);
        $this->assertSame('2026-04-05 12:00:00', $model->created_at);
    }

    public function testSaveForNewModelDoesNotUsePrimaryKeyUpsert(): void
    {
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);
        $db->setMockResults([
            [
                ['id' => 15, 'name' => 'Golf']
            ]
        ]);

        $model = new DummyModel();
        $model->name = 'Golf';

        $this->assertTrue($model->save());

        $query = $db->getLastQuery();
        $this->assertNotNull($query);
        $this->assertStringContainsString('INSERT INTO "dummy_model"', $query['sql']);
        $this->assertStringNotContainsString('ON CONFLICT', strtoupper($query['sql']));
        $this->assertSame(15, $model->id);
    }

    public function testWriteClosesReturningCursor(): void
    {
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);
        $db->setMockResults([
            [
                ['id' => 21, 'name' => 'Hotel']
            ]
        ]);

        $model = new DummyModel();
        $model->name = 'Hotel';
        $this->assertTrue($model->insert());
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
        AppContext::instance()->dbManager()->set('default', $db);
        $db->setMockResults([
            [
                ['id' => 22, 'name' => 'India']
            ]
        ]);

        // A write statement (isReadResultSet=false) produces a ResultSet, but
        // it must not be refreshable — refresh() would re-execute the write.
        $rs = new \Azera\Db\ResultSet(
            $db,
            $db->query('INSERT INTO "dummy_model" ("name") VALUES (\'India\') RETURNING *'),
            'INSERT INTO "dummy_model" ("name") VALUES (\'India\') RETURNING *',
            [],
            DummyModel::class,
            false
        );

        $this->expectException(\Azera\Db\Exception::class);
        $rs->refresh();
    }

    public function testRefreshOnReadResultSetSucceeds(): void
    {
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);
        $db->setMockResults([
            [
                ['id' => 23, 'name' => 'Juliet']
            ]
        ]);

        $rs = new \Azera\Db\ResultSet(
            $db,
            $db->query('SELECT * FROM "dummy_model"'),
            'SELECT * FROM "dummy_model"',
            [],
            DummyModel::class,
            true
        );

        $rs->refresh();
        $this->assertTrue(true, 'Refresh of a read-only result set should not throw');
    }

    public function testFindHydratesModelInstance(): void
    {
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);
        $db->setMockResults([
            [
                ['id' => 5, 'name' => 'Foxtrot']
            ]
        ]);

        $model = DummyModel::find(5);

        $this->assertInstanceOf(DummyModel::class, $model);
        $this->assertSame(5, $model->id);
        $this->assertSame('Foxtrot', $model->name);
        $this->assertNotNull($model->getState());
    }

    public function testCompositeKeyInsertBackfillsAllIdsViaReturningOnMysql(): void
    {
        $db = new TestMysqlDatabase();
        AppContext::instance()->dbManager()->set('default', $db);
        $db->setMockResults([
            [
                ['tenant_id' => 7, 'id' => 42, 'name' => 'Composite']
            ]
        ]);

        $model = new DummyCompositeModel();
        $model->name = 'Composite';

        $this->assertTrue($model->insert());

        $query = $db->getLastQuery();
        $this->assertNotNull($query);
        $this->assertStringContainsString('RETURNING', strtoupper($query['sql']));
        $this->assertSame(7, $model->tenant_id);
        $this->assertSame(42, $model->id);
    }

    public function testCompositeKeyInsertBackfillsAllIdsViaReturningOnSqlite(): void
    {
        $db = new TestSqliteDatabase();
        AppContext::instance()->dbManager()->set('default', $db);
        $db->setMockResults([
            [
                ['tenant_id' => 3, 'id' => 99, 'name' => 'Composite']
            ]
        ]);

        $model = new DummyCompositeModel();
        $model->name = 'Composite';

        $this->assertTrue($model->insert());

        $query = $db->getLastQuery();
        $this->assertNotNull($query);
        $this->assertStringContainsString('RETURNING', strtoupper($query['sql']));
        $this->assertSame(3, $model->tenant_id);
        $this->assertSame(99, $model->id);
    }
}