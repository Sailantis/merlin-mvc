<?php
namespace Merlin\Tests\Db;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/TestDatabase.php';

use Merlin\Db\Sql;
use Merlin\Db\Query;
use PHPUnit\Framework\TestCase;

/**
 * Example test demonstrating usage of enhanced TestDatabase
 * for testing query builders
 */
class BuilderExampleTest extends TestCase
{
    protected function setUp(): void
    {
        // Disable model resolution for simple table testing
        Query::useModels(false);
        Query::setModelMapping(null);
    }

    protected function tearDown(): void
    {
        // Re-enable models after tests
        Query::useModels(true);
        Query::setModelMapping(null);
    }

    public function testSelectBuilderGeneratesCorrectSql(): void
    {
        $db = new TestPgDatabase();

        $builder = $db->builder()
            ->returnSql()
            ->table('users')
            ->where('status', 'active')
            ->where('age >=', 18)
            ->orderBy('created_at DESC')
            ->limit(10);

        $sql = $builder->select();

        $this->assertStringContainsString('SELECT * FROM "users"', $sql);
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('age', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testSelectBuilderWithMockResults(): void
    {
        $db = new TestPgDatabase();

        $builder = $db->builder()
            ->returnSql()
            ->table('users')
            ->where('status', 'active');

        $sql = $builder->select();

        // Verify SQL was generated correctly
        $this->assertStringContainsString('SELECT * FROM "users"', $sql);
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('active', $sql);
        $db = new TestPgDatabase();
        $db->setLastInsertId(42);

        $builder = $db->builder()
            ->returnSql()
            ->table('users')
            ->values([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'status' => 'active'
            ]);

        $sql = $builder->insert();

        $this->assertStringContainsString('INSERT INTO "users"', $sql);
        $this->assertStringContainsString('name', $sql);
        $this->assertStringContainsString('email', $sql);
        $this->assertStringContainsString('status', $sql);
    }

    public function testUpdateBuilderGeneratesCorrectSql(): void
    {
        $db = new TestPgDatabase();

        $builder = $db->builder()
            ->returnSql()
            ->table('users')
            ->values(['status' => 'inactive', 'updated_at' => Sql::raw('NOW()')])
            ->where('id', 123);

        $sql = $builder->update();

        $this->assertStringContainsString('UPDATE "users"', $sql);
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('updated_at', $sql);
        $this->assertStringContainsString('NOW()', $sql);
        $this->assertStringContainsString('WHERE', $sql);
    }

    public function testDeleteBuilderGeneratesCorrectSql(): void
    {
        $db = new TestPgDatabase();

        $builder = $db->builder()
            ->returnSql()
            ->table('users')
            ->where('status', 'deleted')
            ->where('created_at <', '2020-01-01');

        $sql = $builder->delete();

        $this->assertStringContainsString('DELETE FROM "users"', $sql);
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('created_at', $sql);
    }

    public function testQueryLogging(): void
    {
        $db = new TestPgDatabase();
        $db->clearQueries(); // Start fresh

        $builder1 = $db->builder()->returnSql()->table('users')->where('id', 1);
        $sql1 = $builder1->select();

        $builder2 = $db->builder()->returnSql()->table('orders')->where('user_id', 1);
        $sql2 = $builder2->select();

        // Verify queries were generated correctly
        $this->assertStringContainsString('users', $sql1);
        $this->assertStringContainsString('orders', $sql2);

        $db->begin();

        $builder = $db->builder()
            ->table('users')
            ->values(['name' => 'Test']);
        $builder->insert();

        $db->commit();

        // Verify transaction commands were logged
        $this->assertGreaterThanOrEqual(3, count($db->queries));
        $this->assertEquals('BEGIN', $db->queries[0]['sql']);
        $this->assertEquals('COMMIT', $db->queries[count($db->queries) - 1]['sql']);
    }

    public function testMysqlDriver(): void
    {
        $db = new TestMysqlDatabase();

        $builder = $db->builder()
            ->returnSql()
            ->table('users')
            ->where('id', 1);

        $sql = $builder->select();

        // MySQL uses backticks for identifiers
        $this->assertStringContainsString('`users`', $sql);
    }

    public function testInsertValuesInlineEscapedLiterals(): void
    {
        $db = new TestPgDatabase();

        $sql = $db->builder()
            ->returnSql()
            ->table('users')
            ->values([
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'age' => null,
            ])
            ->insert();

        $this->assertStringContainsString('INSERT INTO "users"', $sql);
        $this->assertStringContainsString("'Alice'", $sql);
        $this->assertStringContainsString("'alice@example.com'", $sql);
        $this->assertStringContainsString('NULL', $sql);
        $this->assertStringNotContainsString(':name', $sql);
        $this->assertStringNotContainsString(":email", $sql);
        $this->assertStringNotContainsString("'NULL'", $sql);
    }
}
