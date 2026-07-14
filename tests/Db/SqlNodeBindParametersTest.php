<?php
namespace Merlin\Tests\Db;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/TestDatabase.php';

use Merlin\Db\Sql;
use Merlin\Db\Query;
use Merlin\Core\Model;
use PHPUnit\Framework\TestCase;

/**
 * Test helper to expose protected properties
 */
class TestUpdateBuilder extends Query
{
    public function setTableName(string $tableName): void
    {
        $this->table = $tableName;
    }
}

class SqlBindParametersTest extends TestCase
{
    private TestDatabase $db;

    protected function setUp(): void
    {
        $this->db = new TestDatabase('mysql');
    }

    public function testRawWithoutBindParams(): void
    {
        $node = Sql::raw('view_count + 1');
        $this->assertEmpty($node->getBindParams());
    }

    public function testRawWithSingleBindParam(): void
    {
        $node = Sql::raw('stock - :qty', ['qty' => 5]);
        $this->assertEquals(['qty' => 5], $node->getBindParams());
    }

    public function testRawWithMultipleBindParams(): void
    {
        $node = Sql::raw(
            'CASE WHEN view_count > :threshold THEN :published ELSE :draft END',
            ['threshold' => 100, 'published' => 'published', 'draft' => 'draft']
        );
        $expected = ['threshold' => 100, 'published' => 'published', 'draft' => 'draft'];
        $this->assertEquals($expected, $node->getBindParams());
    }

    public function testUpdateBuilderExtractsBindParams(): void
    {
        $builder = new TestUpdateBuilder($this->db);
        $builder->setTableName('`posts`'); // Set table name directly
        $builder->set('view_count', Sql::raw('view_count + :increment', ['increment' => 10]))
            ->where('id = 5'); // Simple WHERE without bind params

        $sql = $builder->returnSql()->update();

        // Should have parameter from Sql
        $this->assertStringNotContainsString(':increment', $sql);
        $this->assertStringContainsString('+ 10', $sql);
    }

    public function testUpdateBuilderMultipleSqlParams(): void
    {
        $builder = new TestUpdateBuilder($this->db);
        $builder->setTableName('`posts`');
        $builder->set('view_count', Sql::raw('view_count + :bonus', ['bonus' => 50]))
            ->set('like_count', Sql::raw('like_count + :extra', ['extra' => 5]))
            ->where('status = ?', 'published');

        $sql = $builder->returnSql()->update();

        // Should have both Sql parameters
        $this->assertStringNotContainsString(':bonus', $sql);
        $this->assertStringNotContainsString(':extra', $sql);
        $this->assertStringContainsString('+ 50', $sql);
        $this->assertStringContainsString('+ 5', $sql);
    }

    public function testUpdateBuilderCaseExpression(): void
    {
        $builder = new TestUpdateBuilder($this->db);
        $builder->setTableName('`posts`');
        $builder->set('status', Sql::raw(
            'CASE WHEN view_count > :threshold THEN :published ELSE :draft END',
            ['threshold' => 75, 'published' => 'published', 'draft' => 'draft']
        ))
            ->where('id = 3');

        $sql = $builder->returnSql()->update();

        // Should have all three Sql parameters
        $this->assertStringNotContainsString(':threshold', $sql);
        $this->assertStringNotContainsString(':published', $sql);
        $this->assertStringNotContainsString(':draft', $sql);
        $this->assertStringContainsString('> 75', $sql);
    }

    public function testUpdateBuilderGreatestFunction(): void
    {
        $builder = new TestUpdateBuilder($this->db);
        $builder->setTableName('`products`');
        $builder->set('stock', Sql::raw('GREATEST(stock - :qty, :min)', ['qty' => 10, 'min' => 0]))
            ->where('id = 42');

        $sql = $builder->returnSql()->update();

        $this->assertStringNotContainsString(':qty', $sql);
        $this->assertStringNotContainsString(':min', $sql);
        $this->assertStringContainsString('- 10', $sql);
        $this->assertStringContainsString(', 0', $sql);
    }

    public function testSqlGenerationWithBindParams(): void
    {
        $builder = new TestUpdateBuilder($this->db);
        $builder->setTableName('`posts`');
        $builder->set('view_count', Sql::raw('view_count + :increment', ['increment' => 5]))
            ->where('id = 1');

        $sql = $builder->returnSql()->update();

        // SQL should contain the placeholder :increment
        $this->assertStringNotContainsString(':increment', $sql);
        $this->assertStringContainsString('view_count + 5', $sql);
    }

    public function testMultipleUpdatesWithSameSql(): void
    {
        // Create Sql once
        $increment = Sql::raw('view_count + :inc', ['inc' => 1]);

        // Use in two different builders
        $builder1 = new TestUpdateBuilder($this->db);
        $builder1->setTableName('`posts`');
        $builder1->set('view_count', $increment)
            ->where('id = 1');

        $builder2 = new TestUpdateBuilder($this->db);
        $builder2->setTableName('`posts`');
        $builder2->set('view_count', $increment)
            ->where('id = 2');

        // Generate SQL statements first to trigger parameter extraction
        $sql1 = $builder1->returnSql()->update();
        $sql2 = $builder2->returnSql()->update();

        // Both should have the Sql parameter
        $this->assertStringNotContainsString(':inc', $sql1);
        $this->assertStringNotContainsString(':inc', $sql2);
        $this->assertStringContainsString('+ 1', $sql1);
        $this->assertStringContainsString('+ 1', $sql2);
    }

    // ---------------------------------------------------------------
    // Sql::bind() — PDO named parameter tests
    // ---------------------------------------------------------------

    public function testBindUsesPdoBinding(): void
    {
        $node = Sql::bind('qty', 5);
        $this->assertTrue($node->usesPdoBinding());
    }

    public function testRawDoesNotUsePdoBinding(): void
    {
        $node = Sql::raw('stock - :qty', ['qty' => 5]);
        $this->assertFalse($node->usesPdoBinding());
    }

    public function testParamDoesNotUsePdoBinding(): void
    {
        $node = Sql::param('qty');
        $this->assertFalse($node->usesPdoBinding());
    }

    /**
     * Sql::bind() in a WHERE clause keeps :name as a real PDO param
     * (not inlined), visible in the query log of the DB.
     */
    public function testBindKeepsPlaceholderInSql(): void
    {
        $builder = new TestUpdateBuilder($this->db);
        $builder->setTableName('`posts`');
        $builder->set('status', Sql::raw('active'))
            ->where('id', Sql::bind('postId', 42));

        $builder->update(); // execute against TestDatabase

        $lastQuery = $this->db->getLastQuery();
        $this->assertNotNull($lastQuery);
        // SQL must contain the placeholder, not the inlined value
        $this->assertStringContainsString(':postId', $lastQuery['sql']);
        // Value must travel as a PDO param
        $this->assertArrayHasKey('postId', $lastQuery['params']);
        $this->assertSame(42, $lastQuery['params']['postId']);
    }

    public function testBindSurfacesValueInGetBindings(): void
    {
        $builder = new TestUpdateBuilder($this->db);
        $builder->setTableName('`posts`');
        $builder->set('status', Sql::raw('active'))
            ->where('id', Sql::bind('postId', 99));

        $builder->update();

        $lastQuery = $this->db->getLastQuery();
        $this->assertArrayHasKey('postId', $lastQuery['params']);
        $this->assertSame(99, $lastQuery['params']['postId']);
    }

    public function testMultipleBindNodesBubbleUp(): void
    {
        $builder = new TestUpdateBuilder($this->db);
        $builder->setTableName('`posts`');
        $builder->set('status', Sql::raw('active'))
            ->where('id', Sql::bind('postId', 7))
            ->where('author_id', Sql::bind('authorId', 3));

        $builder->update();

        $lastQuery = $this->db->getLastQuery();
        $this->assertStringContainsString(':postId', $lastQuery['sql']);
        $this->assertStringContainsString(':authorId', $lastQuery['sql']);
        $this->assertSame(7, $lastQuery['params']['postId']);
        $this->assertSame(3, $lastQuery['params']['authorId']);
    }

    public function testBindInSetClauseBubblesUp(): void
    {
        $builder = new TestUpdateBuilder($this->db);
        $builder->setTableName('`products`');
        $builder->set('price', Sql::bind('newPrice', 19.99))
            ->where('id = 1');

        $builder->update();

        $lastQuery = $this->db->getLastQuery();
        $this->assertStringContainsString(':newPrice', $lastQuery['sql']);
        $this->assertArrayHasKey('newPrice', $lastQuery['params']);
        $this->assertSame(19.99, $lastQuery['params']['newPrice']);
    }

    /**
     * Sql::raw() with $bindParams still inlines values (existing behavior).
     */
    public function testRawStillInlinesValues(): void
    {
        $builder = new TestUpdateBuilder($this->db);
        $builder->setTableName('`posts`');
        $builder->set('view_count', Sql::raw('view_count + :inc', ['inc' => 5]))
            ->where('id = 1');

        $builder->update();

        $lastQuery = $this->db->getLastQuery();
        // :inc must be replaced with the literal value in the SQL
        $this->assertStringNotContainsString(':inc', $lastQuery['sql']);
        $this->assertStringContainsString('+ 5', $lastQuery['sql']);
        // No PDO params for inlined values
        $this->assertArrayNotHasKey('inc', $lastQuery['params']);
    }
}
