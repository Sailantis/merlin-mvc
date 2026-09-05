<?php
namespace Azera\Tests\Db;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/TestDatabase.php';

use Azera\Db\Sql;
use Azera\Db\Query;
use Azera\AppContext;
use Azera\Db\Condition;
use Azera\Db\ModelMapping;
use Azera\Db\Resolver\MappingResolver;
use PHPUnit\Framework\TestCase;

class SelectBuilderTest extends TestCase
{
    public function testBasicSelectWithJoinGroupOrderLimit(): void
    {
        $db = new TestPgDatabase();
        AppContext::setInstance(new AppContext());
        AppContext::instance()->dbManager()->set('default', $db);
        $sb = Query::raw($db);

        // Use Condition for JOIN to get identifier protection
        $joinCondition = Condition::new($db)->where('o.user_id = u.id');

        $sb->table('users u')
            ->columns(['u.id', 'u.name'])
            ->leftJoin('orders o', $joinCondition)
            ->where('u.id', 1)
            ->orderBy('u.name')
            ->limit(10, 5)
            ->sharedLock(true);

        $expected = 'SELECT "u"."id", "u"."name" FROM "users" AS "u" LEFT JOIN "orders" AS "o" ON (("o"."user_id" = "u"."id")) WHERE ("u"."id" = 1) ORDER BY "u"."name" LIMIT 10 OFFSET 5 FOR SHARE';

        $this->assertEquals($expected, $sb->returnSql()->select());
    }

    public function testConditionResolvesModelToTableAlias(): void
    {
        AppContext::setInstance(new AppContext());

        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);
        $sb = Query::new($db)->using(new MappingResolver(ModelMapping::fromArray([
            'Model' => ['source' => 'user', 'schema' => null],
        ])));

        $sb->table('Model')
            ->columns(['Model.id', 'Model.name']);

        $c = Condition::new()
            ->where('Model.age >=', 18)
            ->where('Model.status', 'active')
            ->group(function (Condition $g) {
                $g->where('Model.role', 'admin')
                    ->orWhere('Model.role', 'moderator');
            });

        $sb->where($c);

        $expected = 'SELECT "user"."id", "user"."name" FROM "user" WHERE (("user"."age" >= 18) AND ("user"."status" = \'active\') AND (("user"."role" = \'admin\') OR ("user"."role" = \'moderator\')))';

        $this->assertEquals($expected, $sb->returnSql()->select());
    }

    public function testModelColumnResolutionWithJoinsAndSqlComposition(): void
    {
        AppContext::setInstance(new AppContext());
        // Test the documentation example: Model.column notation in SelectBuilder with JOINs and Sql composition
        // This is crucial: verifies Model.column resolves to correct table identifiers throughout the query

        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);
        $sb = Query::new($db)->using(new MappingResolver(ModelMapping::fromArray([
            'Order' => ['source' => 'order', 'schema' => 'public'],
            'Customer' => ['source' => 'customer', 'schema' => 'public'],
        ])));

        // Build the query from documentation:
        // $results = Order::selectBuilder()
        //     ->join(Customer::class, 'Customer.id = Order.customer_id')
        //     ->columns([
        //         'Order.id',
        //         Sql::concat(
        //             Sql::column('Customer.first_name'),
        //             Sql::value(' '),
        //             Sql::column('Customer.last_name')
        //         )->as('customer_name'),
        //         Sql::column('Order.total')
        //     ])
        //     ->execute();

        $customerName = Sql::concat(
            Sql::column('Customer.first_name'),
            Sql::value(' '),
            Sql::column('Customer.last_name')
        )->as('customer_name');

        $sb->table('Order')
            ->join('Customer', 'Customer.id = Order.customer_id')
            ->columns([
                'Order.id',
                $customerName,
                'Order.total'
            ]);

        $sql = $sb->returnSql()->select();

        // Verify complete expected SQL structure
        // SELECT o.id, concatenation, o.total FROM order AS o JOIN customer AS c ON ...
        $expected = 'SELECT "order"."id", "customer"."first_name" || \' \' || "customer"."last_name" AS "customer_name", "order"."total" FROM "public"."order" JOIN "public"."customer" ON ("customer"."id" = "order"."customer_id")';

        $this->assertEquals($expected, $sql);
    }

    public function testReusableConditionResolvesPerQueryContext(): void
    {
        AppContext::setInstance(new AppContext());
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);

        $reusable = Condition::new()
            ->where('Model.id', 1)
            ->where('Model.status', 'active');

        $first = Query::new($db)
            ->using(new MappingResolver(ModelMapping::fromArray([
                'Model' => ['source' => 'users', 'schema' => null],
            ])))
            ->table('Model')
            ->where($reusable)
            ->returnSql()
            ->select();

        $this->assertEquals(
            'SELECT * FROM "users" WHERE (("users"."id" = 1) AND ("users"."status" = \'active\'))',
            $first
        );

        $second = Query::new($db)
            ->using(new MappingResolver(ModelMapping::fromArray([
                'Model' => ['source' => 'accounts', 'schema' => null],
            ])))
            ->table('Model')
            ->where($reusable)
            ->returnSql()
            ->select();

        $this->assertEquals(
            'SELECT * FROM "accounts" WHERE (("accounts"."id" = 1) AND ("accounts"."status" = \'active\'))',
            $second
        );
    }

    public function testReusableJoinConditionResolvesPerModelMapping(): void
    {
        AppContext::setInstance(new AppContext());
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);

        $joinCondition = Condition::new()->where('User.id = Order.user_id');

        $first = Query::new($db)
            ->using(new MappingResolver(ModelMapping::fromArray([
                'User' => ['source' => 'users', 'schema' => null],
                'Order' => ['source' => 'orders', 'schema' => null],
            ])))
            ->table('User')
            ->join('Order', $joinCondition)
            ->returnSql()
            ->select();

        $this->assertEquals(
            'SELECT * FROM "users" JOIN "orders" ON (("users"."id" = "orders"."user_id"))',
            $first
        );

        $second = Query::new($db)
            ->using(new MappingResolver(ModelMapping::fromArray([
                'User' => ['source' => 'accounts', 'schema' => null],
                'Order' => ['source' => 'purchases', 'schema' => null],
            ])))
            ->table('User')
            ->join('Order', $joinCondition)
            ->returnSql()
            ->select();

        $this->assertEquals(
            'SELECT * FROM "accounts" JOIN "purchases" ON (("accounts"."id" = "purchases"."user_id"))',
            $second
        );
    }

    public function testReusableBoundConditionResolvesPerModelMapping(): void
    {
        AppContext::setInstance(new AppContext());
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);

        $bound = Condition::new()
            ->where('Model.status = :status')
            ->bind(['status' => 'active']);

        $first = Query::new($db)
            ->using(new MappingResolver(ModelMapping::fromArray([
                'Model' => ['source' => 'users', 'schema' => null],
            ])))
            ->table('Model')
            ->where($bound)
            ->returnSql()
            ->select();

        $this->assertEquals(
            'SELECT * FROM "users" WHERE (("users"."status" = \'active\'))',
            $first
        );

        $second = Query::new($db)
            ->using(new MappingResolver(ModelMapping::fromArray([
                'Model' => ['source' => 'accounts', 'schema' => null],
            ])))
            ->table('Model')
            ->where($bound)
            ->returnSql()
            ->select();

        $this->assertEquals(
            'SELECT * FROM "accounts" WHERE (("accounts"."status" = \'active\'))',
            $second
        );
    }

    public function testReusableBoundJoinConditionResolvesPerModelMapping(): void
    {
        AppContext::setInstance(new AppContext());
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);

        $joinCondition = Condition::new()
            ->where('User.id = Order.user_id')
            ->where('Order.state = :state')
            ->bind(['state' => 'open']);

        $first = Query::new($db)
            ->using(new MappingResolver(ModelMapping::fromArray([
                'User' => ['source' => 'users', 'schema' => null],
                'Order' => ['source' => 'orders', 'schema' => null],
            ])))
            ->table('User')
            ->join('Order', $joinCondition)
            ->returnSql()
            ->select();

        $this->assertEquals(
            'SELECT * FROM "users" JOIN "orders" ON (("users"."id" = "orders"."user_id") AND ("orders"."state" = \'open\'))',
            $first
        );

        $second = Query::new($db)
            ->using(new MappingResolver(ModelMapping::fromArray([
                'User' => ['source' => 'accounts', 'schema' => null],
                'Order' => ['source' => 'purchases', 'schema' => null],
            ])))
            ->table('User')
            ->join('Order', $joinCondition)
            ->returnSql()
            ->select();

        $this->assertEquals(
            'SELECT * FROM "accounts" JOIN "purchases" ON (("accounts"."id" = "purchases"."user_id") AND ("purchases"."state" = \'open\'))',
            $second
        );
    }

    public function testSubQueryInFrom(): void
    {
        AppContext::setInstance(new AppContext());
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);

        $sub = Query::raw($db)
            ->table('orders o')
            ->columns(['o.id', 'o.user_id'])
            ->where('o.total >', 100);

        $q = Query::raw($db)
            ->from($sub, 's')
            ->columns(['s.user_id'])
            ->returnSql()
            ->select();

        $expected = 'SELECT "s"."user_id" FROM (SELECT "o"."id", "o"."user_id" FROM "orders" AS "o" WHERE ("o"."total" > 100)) AS "s"';

        $this->assertEquals($expected, $q);
    }

    public function testSubQueryInJoin(): void
    {
        AppContext::setInstance(new AppContext());
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);

        $sub = Query::raw($db)
            ->table('orders o')
            ->columns(['o.user_id', 'o.id'])
            ->where('o.total >', 100);

        $q = Query::raw($db)
            ->table('users u')
            ->columns(['u.id'])
            ->leftJoin($sub, 'o', Condition::new()->where('o.user_id = u.id'))
            ->returnSql()
            ->select();

        $expected = 'SELECT "u"."id" FROM "users" AS "u" LEFT JOIN (SELECT "o"."user_id", "o"."id" FROM "orders" AS "o" WHERE ("o"."total" > 100)) AS "o" ON (("o"."user_id" = "u"."id"))';

        $this->assertEquals($expected, $q);
    }

    public function testWhereWithSqlSubQuery(): void
    {
        AppContext::setInstance(new AppContext());
        $db = new TestPgDatabase();
        AppContext::instance()->dbManager()->set('default', $db);

        $sub = Query::raw($db)
            ->table('orders o2')
            ->columns(['o2.user_id'])
            ->where('o2.total >', 100);

        $q = Query::raw($db)
            ->table('users u')
            ->inWhere('u.id', $sub)
            ->returnSql()
            ->select();

        $expected = 'SELECT * FROM "users" AS "u" WHERE ("u"."id" IN (SELECT "o2"."user_id" FROM "orders" AS "o2" WHERE ("o2"."total" > 100)))';

        $this->assertEquals($expected, $q);
    }
}