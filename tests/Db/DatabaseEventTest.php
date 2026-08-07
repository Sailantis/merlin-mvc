<?php

namespace Azera\Tests\Db;

require_once __DIR__ . '/TestDatabase.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use Azera\AppContext;
use Azera\Event\EventDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;
use Azera\Db\Event\QueryExecuted;
use Azera\Db\Event\TransactionStarted;
use Azera\Db\Event\TransactionCommitted;
use Azera\Db\Event\TransactionRolledBack;
use Azera\Db\Event\StatementPrepared;
use Azera\Db\Database;

/**
 * Test stub that overrides query/prepare/begin/commit/rollback to avoid
 * real PDO while still dispatching events through the parent's event mechanism.
 */
class EventTestDatabase extends Database
{
    public function __construct()
    {
        // Don't call parent constructor — no real PDO connection
        $this->driverName       = 'sqlite';
        $this->quoteChar        = '"';
        $this->transactionLevel = 0;
    }

    public function query(string $statement, ?array $params = null): bool|\PDOStatement
    {
        $start = microtime(true);
        $this->events()->dispatch(new QueryExecuted(
            $this,
            $statement,
            $params,
            (microtime(true) - $start) * 1000.0
        ));
        return true;
    }

    public function prepare(string $statement): bool|\PDOStatement
    {
        $this->events()->dispatch(new StatementPrepared($this, $statement));
        return true;
    }

    public function begin(bool $nesting = true): bool|int
    {
        $this->transactionLevel++;
        $this->events()->dispatch(
            new TransactionStarted($this, $nesting, $this->transactionLevel)
        );
        return true;
    }

    public function commit(bool $nesting = true): bool|int
    {
        $this->transactionLevel--;
        $this->events()->dispatch(
            new TransactionCommitted($this, $nesting, $this->transactionLevel)
        );
        return true;
    }

    public function rollback(bool $nesting = true): bool|int
    {
        $this->transactionLevel--;
        $this->events()->dispatch(
            new TransactionRolledBack($this, $nesting, $this->transactionLevel)
        );
        return true;
    }
}

class DatabaseEventTest extends TestCase
{
    private EventTestDatabase $db;

    protected function setUp(): void
    {
        AppContext::setInstance(new AppContext());
        $this->db = new EventTestDatabase();
    }

    protected function registerDispatcher(EventDispatcher $dispatcher): void
    {
        AppContext::instance()->set(EventDispatcherInterface::class, $dispatcher);
    }

    public function testQueryFiresQueryExecutedEvent(): void
    {
        $received   = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->listen(QueryExecuted::class, function (QueryExecuted $e) use (&$received) {
            $received[] = $e;
        });
        $this->registerDispatcher($dispatcher);

        $this->db->query('SELECT 1');

        $this->assertCount(1, $received);
        $this->assertSame('SELECT 1', $received[0]->sql);
    }

    public function testQueryFiresWithParams(): void
    {
        $received   = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->listen(QueryExecuted::class, function (QueryExecuted $e) use (&$received) {
            $received[] = $e;
        });
        $this->registerDispatcher($dispatcher);

        $this->db->query('SELECT ? WHERE id = ?', [1, 42]);

        $this->assertCount(1, $received);
        $this->assertSame([1, 42], $received[0]->params);
    }

    public function testPrepareFiresStatementPreparedEvent(): void
    {
        $received   = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->listen(StatementPrepared::class, function (StatementPrepared $e) use (&$received) {
            $received[] = $e;
        });
        $this->registerDispatcher($dispatcher);

        $this->db->prepare('SELECT * FROM users');

        $this->assertCount(1, $received);
        $this->assertSame('SELECT * FROM users', $received[0]->sql);
    }

    public function testBeginFiresTransactionStartedEvent(): void
    {
        $received   = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->listen(TransactionStarted::class, function (TransactionStarted $e) use (&$received) {
            $received[] = $e;
        });
        $this->registerDispatcher($dispatcher);

        $this->db->begin();

        $this->assertCount(1, $received);
        $this->assertSame(1, $received[0]->level);
    }

    public function testCommitFiresTransactionCommittedEvent(): void
    {
        $received   = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->listen(TransactionCommitted::class, function (TransactionCommitted $e) use (&$received) {
            $received[] = $e;
        });
        $this->registerDispatcher($dispatcher);

        $this->db->begin();
        $this->db->commit();

        $this->assertCount(1, $received);
    }

    public function testRollbackFiresTransactionRolledBackEvent(): void
    {
        $received   = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->listen(TransactionRolledBack::class, function (TransactionRolledBack $e) use (&$received) {
            $received[] = $e;
        });
        $this->registerDispatcher($dispatcher);

        $this->db->begin();
        $this->db->rollback();

        $this->assertCount(1, $received);
    }

    public function testNoDispatcherMeansNoErrors(): void
    {
        // Without a registered dispatcher, events go through
        // NullEventDispatcher (no-op) without errors.
        $this->db->query('SELECT 1');
        $this->db->begin();
        $this->db->commit();

        $this->assertTrue(true);
    }

    public function testEventCarriesDatabaseInstance(): void
    {
        $received   = null;
        $dispatcher = new EventDispatcher();
        $dispatcher->listen(QueryExecuted::class, function (QueryExecuted $e) use (&$received) {
            $received = $e;
        });
        $this->registerDispatcher($dispatcher);

        $this->db->query('SELECT 1');

        $this->assertSame($this->db, $received->database);
    }
}