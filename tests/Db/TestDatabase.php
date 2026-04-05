<?php
namespace Merlin\Tests\Db;

/**
 * Lightweight test PDO driver to avoid real DB connection
 * Tracks queries and provides mock result functionality for testing
 */
class TestDatabase extends \Merlin\Db\Database
{
    /** @var array Query log with SQL and parameters */
    public array $queries = [];

    /** @var array Mock results to return for queries */
    protected array $mockResults = [];

    /** @var int Last insert ID to return */
    protected int $lastInsertId = 1;

    /** @var int Affected rows to return */
    protected int $affectedRows = 0;

    /** @var string Driver name */
    protected string $driverName = 'pgsql';

    public function __construct(string $driver = 'pgsql')
    {
        // do not call parent constructor which attempts a DB connection
        $this->driverName = $driver;

        if ($driver === 'mysql') {
            $this->quoteChar = '`';
        } else {
            $this->quoteChar = '"';
        }
    }

    public function getDriver(): string
    {
        return $this->driverName;
    }

    public function quote($str): string
    {
        if ($str === null) {
            return 'NULL';
        }
        return "'" . str_replace("'", "''", (string) $str) . "'";
    }

    /**
     * Mock query execution - logs the query and returns mock result
     */
    public function query($statement, $params = null): TestPdoStatement
    {
        $args = func_get_args();
        array_shift($args); // remove the $statement argument
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        $this->queries[] = [
            'sql' => $statement,
            'params' => $args ?? []
        ];

        // Return mock PDOStatement
        return new TestPdoStatement($this->getNextMockResult());
    }

    /**
     * Mock prepare - logs the query
     */
    public function prepare($query): TestPdoStatement
    {
        return new TestPdoStatement($this->getNextMockResult());
    }

    /**
     * Mock execute
     */
    public function execute($sth = null): bool|TestPdoStatement
    {
        $params = func_get_args();
        array_shift($params);

        if ($sth instanceof TestPdoStatement) {
            $this->queries[] = [
                'sql' => 'prepared statement',
                'params' => $params
            ];
            return $sth;
        }

        return true;
    }

    /**
     * Set mock results for subsequent queries
     */
    public function setMockResults(array $results)
    {
        $this->mockResults = $results;
    }

    /**
     * Add a single mock result
     */
    public function addMockResult(array $result)
    {
        $this->mockResults[] = $result;
    }

    /**
     * Get next mock result
     */
    protected function getNextMockResult()
    {
        if (empty($this->mockResults)) {
            return [];
        }
        return array_shift($this->mockResults);
    }

    /**
     * Get last executed query
     */
    public function getLastQuery(): ?array
    {
        return empty($this->queries) ? null : end($this->queries);
    }

    /**
     * Get last SQL statement
     */
    public function getLastSql(): ?string
    {
        $query = $this->getLastQuery();
        return $query ? $query['sql'] : null;
    }

    /**
     * Clear query log
     */
    public function clearQueries()
    {
        $this->queries = [];
    }

    /**
     * Set last insert ID to return
     */
    public function setLastInsertId(int $id)
    {
        $this->lastInsertId = $id;
    }

    public function lastInsertId($table = null, $field = null): string
    {
        return (string) $this->lastInsertId;
    }

    /**
     * Set affected rows to return
     */
    public function setAffectedRows(int $rows)
    {
        $this->affectedRows = $rows;
    }

    // Transaction methods (no-op for testing)
    public function begin($nesting = true): bool
    {
        $this->queries[] = ['sql' => 'BEGIN', 'params' => []];
        return true;
    }

    public function commit($nesting = true): bool
    {
        $this->queries[] = ['sql' => 'COMMIT', 'params' => []];
        return true;
    }

    public function rollback($nesting = true): bool
    {
        $this->queries[] = ['sql' => 'ROLLBACK', 'params' => []];
        return true;
    }
    /**
     * Return null for getInternalConnection since we don't have a real PDO connection
     */
    #[\ReturnTypeWillChange]
    public function getInternalConnection(): ?\PDO
    {
        static $handler = null;
        if ($handler === null) {
            $handler = new class extends \PDO {
                public function __construct()
                {
                }
                #[\ReturnTypeWillChange]
                public function getAttribute($attr)
                {
                    return \PDO::FETCH_ASSOC;
                }
                #[\ReturnTypeWillChange]
                public function lastInsertId($table = null, $field = null)
                {
                    return '';
                }
            };
        }
        return $handler;
    }
}

/**
 * Mock PDOStatement for testing
 */
class TestPdoStatement extends \PDOStatement
{
    protected array $results = [];
    protected int $position = 0;
    protected int $fetchMode = \PDO::FETCH_BOTH;
    protected ?string $fetchClass = null;

    public function __construct(array $results = [])
    {
        $this->results = $results;
    }

    public function setFetchMode($mode, ...$args): bool
    {
        $this->fetchMode = $mode;
        $this->fetchClass = $mode === \PDO::FETCH_CLASS && isset($args[0]) ? (string)$args[0] : null;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function fetch($mode = \PDO::FETCH_BOTH, $cursorOrientation = \PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        if ($this->position >= count($this->results)) {
            return false;
        }

        $row = $this->results[$this->position++];
		$mode = $mode === \PDO::FETCH_BOTH ? $this->fetchMode : $mode;

        switch ($mode) {
            case \PDO::FETCH_CLASS:
				if ($this->fetchClass === null) {
					return false;
				}
				$object = new $this->fetchClass();
				foreach ($row as $key => $value) {
					$object->$key = $value;
				}
				return $object;
            case \PDO::FETCH_ASSOC:
                return $row;
            case \PDO::FETCH_NUM:
                return array_values($row);
            case \PDO::FETCH_OBJ:
                return (object) $row;
            case \PDO::FETCH_BOTH:
            default:
                return array_merge($row, array_values($row));
        }
    }

    #[\ReturnTypeWillChange]
    public function fetchAll($mode = \PDO::FETCH_BOTH, ...$args): array
    {
        $result = [];
        while ($row = $this->fetch($mode)) {
            $result[] = $row;
        }
        $this->position = 0; // reset for potential re-fetch
        return $result;
    }

    #[\ReturnTypeWillChange]
    public function fetchColumn($column = 0)
    {
        if ($this->position >= count($this->results)) {
            return false;
        }

        $row = array_values($this->results[$this->position++]);
        return $row[$column] ?? false;
    }

    public function rowCount(): int
    {
        return count($this->results);
    }

    public function columnCount(): int
    {
        if (empty($this->results)) {
            return 0;
        }
        return count($this->results[0]);
    }

    public function closeCursor(): bool
    {
        $this->position = 0;
        return true;
    }
}

// PostgreSQL test driver
class TestPgDatabase extends TestDatabase
{
    public function __construct()
    {
        parent::__construct('pgsql');
    }
}

// MySQL test driver
class TestMysqlDatabase extends TestDatabase
{
    public function __construct()
    {
        parent::__construct('mysql');
    }
}

// SQLite test driver
class TestSqliteDatabase extends TestDatabase
{
    public function __construct()
    {
        parent::__construct('sqlite');
    }
}
