<?php
namespace Azera\Tests\Db;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/TestDatabase.php';

use Azera\Db\Sql;
use Azera\Db\Query;
use Azera\Db\Condition;
use PHPUnit\Framework\TestCase;

/**
 * Test case for building complex queries with multiple JOINs,
 * CASE expressions, and string concatenation
 */
class ComplexQueryBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        // Disable model resolution for plain table testing
        Query::useModels(false);
    }

    protected function tearDown(): void
    {
        // Re-enable models after tests
        Query::useModels(true);
    }

    /**
     * Test building a complex customer view query with:
     * - Multiple JOINs
     * - String concatenation
     * - CASE expressions
     * - Column aliases
     * 
     * Original SQL:
     * SELECT cu.customer_id AS id,
     *     (cu.first_name || ' '::text) || cu.last_name AS name,
     *     a.address,
     *     a.postal_code AS "zip code",
     *     a.phone,
     *     city.city,
     *     country.country,
     *     CASE
     *         WHEN cu.activebool THEN 'active'::text
     *         ELSE ''::text
     *     END AS notes,
     *     cu.store_id AS sid
     * FROM customer cu
     *   JOIN address a ON cu.address_id = a.address_id
     *   JOIN city ON a.city_id = city.city_id
     *   JOIN country ON city.country_id = country.country_id;
     */
    public function testComplexCustomerViewQuery(): void
    {
        $db = new TestPgDatabase();
        $sb = new Query($db);

        // Build the query
        $sb->table('customer cu')
            ->columns([
                'cu.customer_id AS id',
                // String concatenation using Sql::concat() - driver-aware!
                Sql::concat(
                    Sql::column('cu.first_name'),
                    ' ',
                    Sql::column('cu.last_name')
                )->as('name'),
                'a.address',
                // Column alias with spaces (will be quoted)
                'a.postal_code AS "zip code"',
                'a.phone',
                'city.city',
                'country.country',
                // CASE expression using Sql::case() fluent builder
                Sql::case()
                    ->when(Sql::column('cu.activebool'), Sql::raw("'active'::text"))
                    ->else(Sql::raw("''::text"))
                    ->end()
                    ->as('notes'),
                'cu.store_id AS sid'
            ])
            ->join('address a', 'cu.address_id = a.address_id')
            ->join('city city', 'a.city_id = city.city_id')
            ->join('country country', 'city.country_id = country.country_id');

        $sql = $sb->returnSql()->select();

        // Verify the query structure
        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('FROM "customer" AS "cu"', $sql);

        // Verify columns
        $this->assertStringContainsString('"cu"."customer_id" AS "id"', $sql);
        // With Sql::concat(), PostgreSQL generates || operator syntax
        $this->assertStringContainsString('"cu"."first_name" || \' \' || "cu"."last_name" AS "name"', $sql);
        $this->assertStringContainsString('"a"."address"', $sql);
        $this->assertStringContainsString('"a"."postal_code" AS "zip code"', $sql);
        $this->assertStringContainsString('"a"."phone"', $sql);
        $this->assertStringContainsString('"city"."city"', $sql);
        $this->assertStringContainsString('"country"."country"', $sql);
        // With Sql::case(), CASE expression is properly structured
        $this->assertStringContainsString("CASE WHEN \"cu\".\"activebool\" THEN 'active'::text ELSE ''::text END AS \"notes\"", $sql);
        $this->assertStringContainsString('"cu"."store_id" AS "sid"', $sql);

        // Verify JOINs
        $this->assertStringContainsString('JOIN "address" AS "a" ON', $sql);
        $this->assertStringContainsString('"cu"."address_id" = "a"."address_id"', $sql);
        $this->assertStringContainsString('JOIN "city" AS "city" ON', $sql);
        $this->assertStringContainsString('"a"."city_id" = "city"."city_id"', $sql);
        $this->assertStringContainsString('JOIN "country" AS "country" ON', $sql);
        $this->assertStringContainsString('"city"."country_id" = "country"."country_id"', $sql);

        // Print the generated SQL for visual verification
        echo "\n\nGenerated SQL:\n" . $sql . "\n\n";
    }

    /**
     * Test building the same query with Condition objects for JOIN conditions
     */
    public function testComplexCustomerViewQueryWithConditionObjects(): void
    {
        $db = new TestPgDatabase();
        $sb = new Query($db);

        // Create Condition objects for joins (provides better identifier protection)
        $addressJoinCondition = Condition::new($db)->where('cu.address_id = a.address_id');
        $cityJoinCondition = Condition::new($db)->where('a.city_id = city.city_id');
        $countryJoinCondition = Condition::new($db)->where('city.country_id = country.country_id');

        // Build the query
        $sb->table('customer cu')
            ->columns([
                'cu.customer_id AS id',
                "(cu.first_name || ' '::text) || cu.last_name AS name",
                'a.address',
                'a.postal_code AS "zip code"',
                'a.phone',
                'city.city',
                'country.country',
                "CASE WHEN cu.activebool THEN 'active'::text ELSE ''::text END AS notes",
                'cu.store_id AS sid'
            ])
            ->join('address a', $addressJoinCondition)
            ->join('city', $cityJoinCondition)
            ->join('country', $countryJoinCondition);

        $sql = $sb->returnSql()->select();

        // Verify the query structure
        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('FROM "customer" AS "cu"', $sql);
        $this->assertStringContainsString('JOIN "address" AS "a"', $sql);
        $this->assertStringContainsString('JOIN "city"', $sql);
        $this->assertStringContainsString('JOIN "country"', $sql);

        // Print the generated SQL for visual verification
        echo "\n\nGenerated SQL (with Condition objects):\n" . $sql . "\n\n";
    }

    /**
     * Test a simpler variant with filtering
     */
    public function testComplexQueryWithWhere(): void
    {
        $db = new TestPgDatabase();
        $sb = new Query($db);

        $sb->table('customer cu')
            ->columns([
                'cu.customer_id AS id',
                "(cu.first_name || ' ' || cu.last_name) AS full_name",
                'a.address',
                'city.city'
            ])
            ->join('address a', 'cu.address_id = a.address_id')
            ->join('city city', 'a.city_id = city.city_id')
            ->where('cu.activebool', true)
            ->where('city.city', 'New York')
            ->orderBy('cu.last_name, cu.first_name')
            ->limit(10);

        $sql = $sb->returnSql()->select();

        // Verify WHERE clause
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('"cu"."activebool" = TRUE', $sql);
        $this->assertStringContainsString('"city"."city" = \'New York\'', $sql);

        // Verify ORDER BY and LIMIT
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);

        // Print the generated SQL
        echo "\n\nGenerated SQL (with WHERE and ORDER BY):\n" . $sql . "\n\n";
    }

    /**
     * Test using INNER JOIN explicitly
     */
    public function testComplexQueryWithInnerJoin(): void
    {
        $db = new TestPgDatabase();
        $sb = new Query($db);

        $sb->table('customer cu')
            ->columns([
                'cu.customer_id',
                'cu.first_name',
                'cu.last_name',
                'a.address'
            ])
            ->innerJoin('address a', 'cu.address_id = a.address_id')
            ->where('cu.activebool', true);

        $sql = $sb->returnSql()->select();

        // Verify INNER JOIN
        $this->assertStringContainsString('INNER JOIN', $sql);

        // Print the generated SQL
        echo "\n\nGenerated SQL (with INNER JOIN):\n" . $sql . "\n\n";
    }

    /**
     * Test LEFT JOIN variant
     */
    public function testComplexQueryWithLeftJoin(): void
    {
        $db = new TestPgDatabase();
        $sb = new Query($db);

        $sb->table('customer cu')
            ->columns([
                'cu.customer_id',
                'cu.first_name',
                'cu.last_name',
                // Using COALESCE in a complex expression
                "COALESCE(a.address, 'No address') AS address"
            ])
            ->leftJoin('address a', 'cu.address_id = a.address_id')
            ->where('cu.activebool', true);

        $sql = $sb->returnSql()->select();

        // Verify LEFT JOIN
        $this->assertStringContainsString('LEFT JOIN', $sql);
        $this->assertStringContainsString('COALESCE', $sql);

        // Print the generated SQL
        echo "\n\nGenerated SQL (with LEFT JOIN):\n" . $sql . "\n\n";
    }

    /**
     * Test driver portability: same Sql code generates different SQL for MySQL vs PostgreSQL
     */
    public function testDriverPortabilityConcatPostgreSQLvsMySQL(): void
    {
        // PostgreSQL version
        $dbPg = new TestPgDatabase();
        $sbPg = new Query($dbPg);

        $sbPg->table('customer cu')
            ->columns([
                'cu.customer_id AS id',
                Sql::concat(
                    Sql::column('cu.first_name'),
                    ' ',
                    Sql::column('cu.last_name')
                )->as('full_name')
            ])
            ->limit(5);

        $sqlPg = $sbPg->returnSql()->select();

        // MySQL version (same code!)
        $dbMysql = new TestMysqlDatabase();
        $sbMysql = new Query($dbMysql);

        $sbMysql->table('customer cu')
            ->columns([
                'cu.customer_id AS id',
                Sql::concat(
                    Sql::column('cu.first_name'),
                    ' ',
                    Sql::column('cu.last_name')
                )->as('full_name')
            ])
            ->limit(5);

        $sqlMysql = $sbMysql->returnSql()->select();

        // PostgreSQL should use || operator
        $this->assertStringContainsString('||', $sqlPg);
        $this->assertStringNotContainsString('CONCAT(', $sqlPg);

        // MySQL should use CONCAT() function
        $this->assertStringContainsString('CONCAT(', $sqlMysql);
        $this->assertStringNotContainsString('||', $sqlMysql);

        echo "\n\nPostgreSQL SQL:\n" . $sqlPg . "\n\n";
        echo "MySQL SQL:\n" . $sqlMysql . "\n\n";
    }

    /**
     * Test complex CASE expression with Sql composition
     */
    public function testComplexCaseExpressionWithSql(): void
    {
        $db = new TestPgDatabase();
        $sb = new Query($db);

        $sb->table('customer cu')
            ->columns([
                'cu.customer_id',
                Sql::case()
                    ->when(Sql::column('cu.activebool'), 'Active Customer')
                    ->else('Inactive')
                    ->end()
                    ->as('status'),
                // Nested: CASE returning a CONCAT result
                Sql::case()
                    ->when(
                        Sql::column('cu.activebool'),
                        Sql::concat(
                            Sql::column('cu.first_name'),
                            ' ',
                            Sql::column('cu.last_name')
                        )
                    )
                    ->else('N/A')
                    ->end()
                    ->as('display_name')
            ])
            ->limit(10);

        $sql = $sb->returnSql()->select();

        $this->assertStringContainsString('CASE WHEN', $sql);
        $this->assertStringContainsString('THEN', $sql);
        $this->assertStringContainsString('ELSE', $sql);
        $this->assertStringContainsString('END', $sql);
        $this->assertStringContainsString('AS "status"', $sql);
        $this->assertStringContainsString('AS "display_name"', $sql);

        echo "\n\nGenerated SQL (complex CASE with Sql):\n" . $sql . "\n\n";
    }

    /**
     * Ensure join conditions containing lowercase 'and' are split and protected correctly
     */
    public function testJoinConditionWithAndProducesValidCountSql(): void
    {
        $db = new TestMysqlDatabase();
        $q = new \Azera\Db\Query($db);

        $q->table('v2_bible_text text')
            ->join('v2_bible_book book', 'book.translation_id = text.translation_id and book.number = text.book_number')
            ->join('v2_bible_translation tr', 'tr.id = book.translation_id')
            ->where("text.type = 'heading'")
            ->where('MATCH (text.text) AGAINST (:keywords IN BOOLEAN MODE)')
            ->where("book.language = 'de'")
            ->bind(['keywords' => 'foo']);

        $sql = $q->returnSql()->count();

        // COUNT wrapper present
        $this->assertStringContainsString('COUNT(*)', $sql);

        // Both comparisons must be protected and joined with AND
        $this->assertStringContainsString('`book`.`translation_id` = `text`.`translation_id`', $sql);
        $this->assertStringContainsString('`book`.`number` = `text`.`book_number`', $sql);
        $this->assertStringContainsString('AND', $sql);
    }
}
