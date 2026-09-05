<?php
namespace Azera\Tests\Db;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/TestDatabase.php';

use Azera\AppContext;
use Azera\Db\Query;
use Azera\Db\Sql;
use PHPUnit\Framework\TestCase;

/**
 * Derived-shape UPSERT regression tests.
 *
 * With NO explicit updateValues(), compileInsert() must derive the SET
 * clause from the INSERT columns as EXCLUDED refs (sqlite/pgsql) or
 * VALUES() refs (mysql), EXCLUDING the conflict target. Writing the PK
 * (or rebinding literals) into SET instead forces SQLite to compile the
 * conflict action as an internal DELETE+INSERT — fsync-bound (~1.2-5.7ms
 * vs ~8µs per statement on WAL SQLite). These tests pin the fast shape
 * so the builder can't regress into the slow one.
 */
class UpsertShapeTest extends TestCase
{
    protected function setUp(): void
    {
        AppContext::setInstance(new AppContext());
    }

    /** Raw sqlite upsert: PK first in the INSERT column list, no conflict() call. */
    public function testSqliteDerivedShapeExcludesFirstColumnAsTarget(): void
    {
        $db = new TestSqliteDatabase();

        $sql = Query::raw($db)
            ->returnSql()
            ->table('items')
            ->upsert([
                'id'         => 999999,
                'title'      => 'Created Item',
                'created_at' => '2026-09-05 12:00:00',
            ]);

        $this->assertStringContainsString('INSERT INTO "items"', $sql);
        $this->assertStringContainsString(' ON CONFLICT DO UPDATE SET ', $sql);

        // The fast shape: EXCLUDED refs for the non-PK columns, and the PK
        // ("id") must NEVER appear on the left side of a SET assignment.
        $this->assertStringContainsString('"title"=EXCLUDED."title"', $sql);
        $this->assertStringContainsString('"created_at"=EXCLUDED."created_at"', $sql);
        $this->assertMatchesRegularExpression('/DO UPDATE SET (?!.*"id"\s*=)/', $sql);

        // Literally-stable: no value literals may leak into the SET clause.
        $set = substr($sql, (int) strpos($sql, 'DO UPDATE SET '));
        $this->assertStringNotContainsString('Created Item', $set);
        $this->assertStringNotContainsString('2026-09-05', $set);
    }

    /** Raw sqlite upsert with an explicit conflict() column list. */
    public function testSqliteExplicitConflictTargetIsExcludedFromSet(): void
    {
        $db = new TestSqliteDatabase();

        $sql = Query::raw($db)
            ->returnSql()
            ->table('users')
            ->conflict(['email'])
            ->upsert([
                'email' => 'john@example.com',
                'name'  => 'John',
            ]);

        $set = substr($sql, (int) strpos($sql, 'DO UPDATE SET '));
        $this->assertStringContainsString('"name"=EXCLUDED."name"', $set);
        $this->assertStringNotContainsString('"email"', $set);
    }

    /** Model-backed query: the metadata idFields() are the implicit target. */
    public function testModelModeExcludesIdFieldsFromDerivedSet(): void
    {
        $db = new TestSqliteDatabase();
        AppContext::instance()->dbManager()->set('default', $db);

        $sql = DummyUpsertModel::query()->returnSql()->upsert([
            'id'   => 5,
            'name' => 'Echo',
        ]);

        $this->assertStringContainsString('INSERT INTO "dummy_upsert_model"', $sql);
        $this->assertStringContainsString('DO UPDATE SET', $sql);
        $this->assertStringContainsString('"name"=EXCLUDED."name"', $sql);
        $this->assertStringNotContainsString('"id"=', $sql);
    }

    /** MySQL uses the VALUES(col) dialect in the derived shape. */
    public function testMysqlDerivedShapeUsesValuesRefs(): void
    {
        $db = new TestMysqlDatabase();

        $sql = Query::raw($db)
            ->returnSql()
            ->table('items')
            ->upsert([
                'id'    => 999999,
                'title' => 'Created Item',
            ]);

        $this->assertStringContainsString(' ON DUPLICATE KEY UPDATE ', $sql);
        $this->assertStringContainsString('`title`=VALUES(`title`)', $sql);
        $set = substr($sql, (int) strpos($sql, 'ON DUPLICATE KEY UPDATE '));
        $this->assertStringNotContainsString('`id`', $set);
    }

    /** PostgreSQL derives the target from explicit conflict() too. */
    public function testPgsqlDerivedShapeExcludesConflictTarget(): void
    {
        $db = new TestPgDatabase();

        $sql = Query::raw($db)
            ->returnSql()
            ->table('items')
            ->conflict(['id'])
            ->upsert([
                'id'    => 999999,
                'title' => 'Created Item',
            ]);

        $this->assertStringContainsString('ON CONFLICT ("id") DO UPDATE SET', $sql);
        $set = substr($sql, (int) strpos($sql, 'DO UPDATE SET '));
        $this->assertStringContainsString('"title"=EXCLUDED."title"', $set);
        $this->assertStringNotContainsString('"id"', $set);
    }

    /** Explicit list updateValues keeps the legacy documented behavior. */
    public function testExplicitListUpdateValuesStillWinsOverDerivation(): void
    {
        $db = new TestSqliteDatabase();

        $sql = Query::raw($db)
            ->returnSql()
            ->table('items')
            ->conflict(['id'])
            ->updateValues(['title', 'created_at'])
            ->upsert([
                'id'         => 999999,
                'title'      => 'Created Item',
                'created_at' => '2026-09-05 12:00:00',
            ]);

        $set = substr($sql, (int) strpos($sql, 'DO UPDATE SET '));
        $this->assertSame('DO UPDATE SET "title"=EXCLUDED."title","created_at"=EXCLUDED."created_at"', trim($set));
    }

    /** Explicit assoc updateValues (incl. custom Sql expressions) is untouched. */
    public function testExplicitAssocUpdateValuesStillWinsOverDerivation(): void
    {
        $db = new TestSqliteDatabase();

        $sql = Query::raw($db)
            ->returnSql()
            ->table('posts')
            ->conflict(['slug'])
            ->updateValues([
                'title'      => 'My Article',
                'view_count' => Sql::raw('view_count + 1'),
            ])
            ->upsert([
                'slug'  => 'my-article',
                'title' => 'My Article',
            ]);

        $set = substr($sql, (int) strpos($sql, 'DO UPDATE SET '));
        $this->assertStringContainsString("'My Article'", $set);
        $this->assertStringContainsString('view_count + 1', $set);
    }

    /** Named-bind upserts: returnSql() interpolates the bound values; the
     * PK must still never appear in the SET clause. */
    public function testManualBindingsKeepPlaceholderSetShape(): void
    {
        $db = new TestSqliteDatabase();

        $sql = Query::raw($db)
            ->returnSql()
            ->table('items')
            ->conflict(['id'])
            ->bind(['id' => 1, 'title' => 'Bound'])
            ->upsert();

        $set = substr($sql, (int) strpos($sql, 'DO UPDATE SET '));
        $this->assertStringContainsString('"title"=', $set);
        $this->assertStringNotContainsString('"id"=', $set);
    }

    /** Derived shape with EVERY column in the conflict target is unusable. */
    public function testAllColumnsInTargetThrows(): void
    {
        $db = new TestSqliteDatabase();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('no columns to update');

        Query::raw($db)
            ->returnSql()
            ->table('kv')
            ->conflict(['k', 'v'])
            ->upsert(['k' => 'a', 'v' => 'b']);
    }
}

class DummyUpsertModel extends \Azera\Orm\Model
{
    public $id;
    public $name;

    public function source(): string
    {
        return 'dummy_upsert_model';
    }
}