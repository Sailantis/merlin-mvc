<?php

namespace Azera\Tests\Orm;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Db/TestDatabase.php';

use Azera\AppContext;
use Azera\Facade\Db;
use Azera\Facade\Tx;
use Azera\Tests\Db\TestDatabase;
use PHPUnit\Framework\TestCase;

class FacadeTest extends TestCase
{
    private TestDatabase $db;

    protected function setUp(): void
    {
        AppContext::setInstance(new AppContext());
        $this->db = new TestDatabase('pgsql');
        AppContext::instance()->dbManager()->set('default', $this->db);
    }

    public function testDbQueryReturnsQueryBuilder(): void
    {
        $q = Db::query();

        $this->assertInstanceOf(\Azera\Db\Query::class, $q);
    }

    public function testDbStatementTracksEvents(): void
    {
        $this->db->setMockResults([[['cnt' => 1]]]);

        Db::statement('SELECT 1');

        $this->assertSame('SELECT 1', $this->db->getLastSql());
    }

    public function testDbTransactionCommitOnSuccess(): void
    {
        $result = Db::transaction(function ($db) {
            return 'ok';
        });

        $this->assertSame('ok', $result);
        $sqls = array_column($this->db->queries, 'sql');
        $this->assertContains('BEGIN', $sqls);
        $this->assertContains('COMMIT', $sqls);
    }

    public function testDbTransactionRollbackOnThrow(): void
    {
        try {
            Db::transaction(function () {
                throw new \RuntimeException('boom');
            });
            $this->fail('expected exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $sqls = array_column($this->db->queries, 'sql');
        $this->assertContains('ROLLBACK', $sqls);
    }

    public function testTxBeginCommitLevel(): void
    {
        $this->assertFalse(Tx::level());
        Tx::begin();
        $this->assertTrue(Tx::level());
        Tx::commit();
        $this->assertFalse(Tx::level());
    }
}