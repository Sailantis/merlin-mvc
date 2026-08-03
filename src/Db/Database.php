<?php

namespace Azera\Db;

use Azera\Db\Exceptions\TransactionLostException;
use Azera\Core\Model;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Class Database
 */
class Database
{
	protected string $connectString;

	protected string $user;

	protected string $driverName;

	protected string $pass;

	protected array $options;

	protected PDO $pdo;

	protected PDOStatement $statement;

	protected int $transactionLevel = 0;

	protected string $quoteChar = '"';

	protected bool|array $autoReconnect = false;

	/** Event listeners for database events */
	protected array $listeners = [];

	/**
	 * Create a new database connection using the provided DSN, credentials and options.
	 * @param string $dsn
	 * @param string $user
	 * @param string $pass
	 * @param array $options
	 * @throws Exception
	 */
	public function __construct(
		string $dsn,
		string $user = "",
		string $pass = "",
		array $options = []
	)
	{
		$this->connectString = $dsn;
		$this->user          = $user;
		$this->pass          = $pass;

		$this->options = $options + [
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
		];

		// Extract driver name from DSN
		$driver = strstr($dsn, ':', true);
		if ($driver === false) {
			throw new Exception("Invalid DSN string: $dsn");
		}
		$this->driverName = strtolower($driver);

		if ($this->driverName === 'mysql') {
			// ANSI quotes requires extra query. For performance reasons we will stick with backticks for MySQL.
			$this->quoteChar = '`';
		}

		$this->connect();
	}

	/**
	 * Establish a new PDO connection using the current configuration
	 * @throws Exception
	 */
	public function connect()
	{
		$this->pdo = new PDO(
			$this->connectString,
			$this->user,
			$this->pass,
			$this->options
		);
		$this->transactionLevel = 0;
	}

	/**
	 * Add an event listener for database events
	 * @param callable $listener A callable that receives the event name and relevant data
	 * @return static
	 */
	public function addListener(callable $listener): static
	{
		$this->listeners[] = $listener;
		return $this;
	}

	protected function fire(string $event, ...$args): void
	{
		foreach ($this->listeners as $listener) {
			$listener($event, ...$args);
		}
	}

	/**
	 * Configure automatic reconnection behavior with detailed options
	 * @param bool $enabled Enable or disable auto-reconnect
	 * @param int $maxAttempts Maximum number of retry attempts (0 for unlimited)
	 * @param float $retryDelay Initial delay between retries in seconds
	 * @param float $backoffMultiplier Multiplier for exponential backoff
	 * @param float $maxRetryDelay Maximum delay between retries in seconds
	 * @param bool $jitter Whether to add random jitter to retry delays
	 * @param callable|null $onReconnect Optional callback invoked on successful reconnect (receives attempt number and db instance)
	 * @return $this
	 */
	public function setAutoReconnect(
		bool $enabled = true,
		int $maxAttempts = 0,
		float $retryDelay = 1.0,
		float $backoffMultiplier = 2.0,
		float $maxRetryDelay = 30.0,
		bool $jitter = true,
		?callable $onReconnect = null
	): static
	{
		$this->autoReconnect = [
			'enabled'           => $enabled,
			'maxAttempts'       => $maxAttempts > 0 ? $maxAttempts : null,
			'retryDelay'        => $retryDelay,
			'backoffMultiplier' => $backoffMultiplier,
			'maxRetryDelay'     => $maxRetryDelay,
			'jitter'            => $jitter,
			'onReconnect'       => $onReconnect,
		];
		return $this;
	}

	/**
	 * Get auto-reconnect configuration
	 * @return bool|array
	 */
	public function getAutoReconnect(): bool|array
	{
		return $this->autoReconnect;
	}

	/**
	 * Execute a SQL statement with optional parameters and return the resulting statement or success status.
	 * @param string $statement SQL statement to execute
	 * @param array|null $params Optional parameters for prepared statements
	 * @return bool|PDOStatement
	 * @throws Exception
	 */
	public function query(string $statement, ?array $params = null): bool|PDOStatement
	{
		retry:
		try {
			$this->fire('db.beforeQuery', $statement, $params);
			if (!empty($params)) {
				$stmt = $this->pdo->prepare($statement);
				$stmt->execute($params);
			} else {
				$stmt = $this->pdo->query($statement);
			}
			if ($stmt === false) {
				if ($stmt === false) {
					$info          = $this->pdo->errorInfo();
					$ex            = new PDOException($info[2] ?? 'Unknown error');
					$ex->errorInfo = $info;
					throw $ex;
				}
			}
		} catch (PDOException $exception) {
			$this->processPdoException($exception);
			goto retry;
		} finally {
			$this->fire('db.afterQuery', $statement, $params);
		}
		$this->statement = $stmt;
		return ($stmt->columnCount() > 0) ? $stmt : true;
	}

	/**
	 * Prepare a SQL statement and return the resulting PDOStatement object.
	 * @param string $statement SQL statement to prepare
	 * @return PDOStatement
	 * @throws \Exception
	 */
	public function prepare(string $statement): bool|PDOStatement
	{
		retry:
		try {
			$this->fire('db.beforePrepare', $statement);
			$stmt = $this->pdo->prepare($statement);
			if ($stmt === false) {
				$info          = $this->pdo->errorInfo();
				$ex            = new PDOException($info[2] ?? 'Unknown error');
				$ex->errorInfo = $info;
				throw $ex;
			}
		} catch (PDOException $exception) {
			$this->processPdoException($exception);
			goto retry;
		} finally {
			$this->fire('db.afterPrepare', $statement);
		}
		$this->statement = $stmt;
		return $stmt;
	}

	/**
	 * Execute the most recently prepared statement with the given bound parameters.
	 * @param array $params Optional parameters to bind for this execution
	 * @return bool|PDOStatement Returns the PDOStatement for SELECT-like queries or true for others
	 * @throws RuntimeException If no prepared statement is available
	 * @throws Exception On database errors
	 */
	public function execute(array $params = []): bool|PDOStatement
	{
		if (empty($this->statement)) {
			throw new RuntimeException(
				"No prepared statement to execute"
			);
		}
		try {
			$this->fire('db.beforeExecute', $this->statement, $params);

			$ok = $this->statement->execute($params);

			if ($ok === false) {
				$info          = $this->statement->errorInfo();
				$ex            = new PDOException($info[2] ?? 'Unknown error');
				$ex->errorInfo = $info;
				throw $ex;
			}

		} catch (PDOException $exception) {
			$this->processPdoException($exception);
			throw $exception;
		} finally {
			$this->fire('db.afterExecute', $this->statement, $params);
		}

		return ($this->statement->columnCount() > 0) ? $this->statement : true;
	}

	/**
	 * @param PDOException $exception
	 * @throws Exception
	 */
	protected function processPdoException(PDOException $exception)
	{
		$this->fire('db.exception', $exception);
		$inTransaction = !empty($this->transactionLevel);
		switch ($exception->errorInfo[1]) {
			case 1213:

			case '40P01':

			case 40001:
				// Deadlock or timeout with automatic rollback occurred.
				if ($inTransaction) {
					throw new TransactionLostException(
						"Deadlock found when trying to get lock; try restarting transaction",
						40001,
						$exception
					);
				}
				// Re-run last command
				return;
			case 2006:

			case 2013:

			case 8001:
			case 8004:
			case 8006:
				// PGSQL: 8006 Connection Exception
				$this->handleReconnect($exception);
				// Re-run last command
				return;
		}
		throw $exception;
	}

	protected function handleReconnect(?\Exception $exception = null)
	{
		// Get auto-reconnect configuration
		$config = is_array($this->autoReconnect)
			? $this->autoReconnect
			: ['enabled' => $this->autoReconnect];

		// Check if auto-reconnect is enabled
		if (!($config['enabled'] ?? false)) {
			return;
		}

		$inTransaction = !empty($this->transactionLevel);

		// Get configuration with defaults
		$maxAttempts       = $config['maxAttempts'] ?? null; // null = unlimited
		$retryDelay        = $config['retryDelay'] ?? 1.0;
		$backoffMultiplier = $config['backoffMultiplier'] ?? 2.0;
		$maxRetryDelay     = $config['maxRetryDelay'] ?? 30.0;
		$useJitter         = $config['jitter'] ?? true;
		$reconnectCallback = $config['onReconnect'] ?? null;

		$attempt      = 1;
		$currentDelay = $retryDelay;

		while ($maxAttempts === null || $attempt <= $maxAttempts) {
			$this->fire(
				'db.reconnectAttempt',
				$attempt,
				$currentDelay,
				$exception
			);

			// Sleep with optional jitter
			if ($attempt > 1) {
				// Don't sleep on first attempt
				$sleepTime = $currentDelay;
				if ($useJitter) {
					// Add ±25% jitter to prevent thundering herd
					$jitterRange = $sleepTime * 0.25;
					$sleepTime += (mt_rand() / mt_getrandmax() * $jitterRange * 2) - $jitterRange;
				}

				if ($sleepTime >= 1) {
					sleep((int) $sleepTime);
					$remaining = $sleepTime - (int) $sleepTime;
					if ($remaining > 0) {
						usleep((int) ($remaining * 1000000));
					}
				} else {
					usleep((int) ($sleepTime * 1000000));
				}
			}

			try {
				$this->connect();

				// Success! Invoke callback if configured
				if ($reconnectCallback !== null) {
					try {
						($reconnectCallback)($attempt, $this);
					} catch (\Exception $callbackEx) {
						$this->fire(
							'db.reconnectCallbackFailed',
							$callbackEx,
							$attempt
						);
					}
				}

				$this->fire('db.reconnected', $attempt);

				if ($inTransaction) {
					throw new TransactionLostException(
						"Lost transaction during reconnect",
						8001,
						$exception
					);
				}

				// Re-run last command
				return;

			} catch (\Exception $reconnectEx) {
				$this->fire('db.reconnectFailed', $reconnectEx, $attempt);

				// Calculate next delay with exponential backoff
				$currentDelay = min($currentDelay * $backoffMultiplier, $maxRetryDelay);
				$attempt++;
			}
		}

		// All retry attempts exhausted
		$this->fire('db.reconnectAborted', $attempt);
	}

	/**
	 * Fetch a single row from the database as object, associative array, or numeric array depending on the specified fetch mode.
	 * @param string $query
	 * @param array|null $params
	 * @param int $fetchMode
	 * @return array|bool
	 */
	public function selectRow(string $query, ?array $params = null, int $fetchMode = PDO::FETCH_DEFAULT): array|bool
	{
		$sth = $this->query($query, $params);
		$row = $sth->fetch($fetchMode);
		$sth->closeCursor();
		return $row;
	}

	/**
	 * Fetch all rows from the database as an array of objects, associative arrays, or numeric arrays depending on the specified fetch mode.
	 * @param string $query
	 * @param array|null $params
	 * @param int $fetchMode
	 * @return array
	 */
	public function selectAll(string $query, ?array $params = null, int $fetchMode = PDO::FETCH_DEFAULT): array
	{
		$sth    = $this->query($query, $params);
		$result = $sth->fetchAll($fetchMode);
		$sth->closeCursor();
		return $result;
	}

	/**
	 * Return the number of rows affected by the last executed statement.
	 * @return int Number of affected rows, or 0 if no statement has been executed.
	 */
	public function rowCount(): int
	{
		return isset($this->statement) ? $this->statement->rowCount() : 0;
	}

	/**
	 * Get the ID generated by the last INSERT statement.
	 * For PostgreSQL, pass the table and primary key field to use currval(pg_get_serial_sequence()).
	 * @param string|null $table Table name (PostgreSQL only).
	 * @param string|null $field Primary key field name (PostgreSQL only).
	 * @return string|bool The last insert ID as a string, or false on failure.
	 */
	public function lastInsertId(?string $table = null, ?string $field = null): string|bool
	{
		if (!empty($table) && !empty($field) && $this->driverName === 'pgsql') {
			if ($table instanceof Model) {
				$schema = $table->schema();
				$table  = $table->source();
				if (!empty($schema)) {
					$table = "$schema.$table";
				}
			}
			$stmt = $this->pdo->prepare(
				"SELECT currval(pg_catalog.pg_get_serial_sequence(:table, :field))"
			);
			if ($stmt === false) {
				$info          = $this->pdo->errorInfo();
				$ex            = new PDOException($info[2] ?? 'Unknown error');
				$ex->errorInfo = $info;
				throw $ex;
			}
			$stmt->execute(
				[
					':table' => $table,
					':field' => $field
				]
			);
			return $stmt->fetchColumn();
		}
		return $this->pdo->lastInsertId();
	}

	/**
	 * Begin a new transaction, or create a savepoint if nested transactions are enabled and a transaction is already active.
	 * @param bool $nesting Whether to use savepoints for nested transactions (if supported by the driver).
	 * @return bool|int True or the number of affected rows on success.
	 * @throws RuntimeException If the transaction cannot be started.
	 */
	public function begin(bool $nesting = true): bool|int
	{
		try {
			$this->transactionLevel++;
			$this->fire('db.beforeBegin', $nesting, $this->transactionLevel);
			if ($this->transactionLevel === 1) {
				$result = $this->pdo->beginTransaction();
			} elseif ($nesting) {
				switch ($this->driverName) {
					case 'mysql':
					case 'pgsql':
					case 'sqlite':
						$result = $this->pdo->exec(
							"SAVEPOINT trans$this->transactionLevel"
						);
						break;
					default:
						$result = $this->pdo->beginTransaction();
				}
			} else {
				return false;
			}
			if ($result === false) {
				$info          = $this->pdo->errorInfo();
				$ex            = new PDOException($info[2] ?? 'Unknown error');
				$ex->errorInfo = $info;
				throw $ex;
			}
			return $result;
		} catch (PDOException $exception) {
			$this->processPdoException($exception);
			throw $exception;
		} finally {
			$this->fire('db.afterBegin', $nesting, $this->transactionLevel);
		}
	}

	/**
	 * Commit the current transaction or release the current savepoint (for nested transactions).
	 * @param bool $nesting Whether to use savepoints for nested transactions (if supported by the driver).
	 * @return bool|int True or the number of affected rows on success.
	 * @throws RuntimeException If there is no active transaction.
	 */
	public function commit(bool $nesting = true): bool|int
	{
		if ($this->transactionLevel === 0) {
			throw new RuntimeException(
				"There is no active transaction"
			);
		}
		try {
			$this->fire('db.beforeCommit', $nesting, $this->transactionLevel);
			$level = $this->transactionLevel--;
			if ($level === 1) {
				$result = $this->pdo->commit();
			} elseif ($nesting) {
				switch ($this->driverName) {
					case 'mysql':
					case 'pgsql':
					case 'sqlite':
						$result = $this->pdo->exec("RELEASE SAVEPOINT trans$level");
						break;
					default:
						$result = $this->pdo->commit();
				}
			} else {
				return false;
			}
			if ($result === false) {
				$info          = $this->pdo->errorInfo();
				$ex            = new PDOException($info[2] ?? 'Unknown error');
				$ex->errorInfo = $info;
				throw $ex;
			}
			return $result;
		} catch (PDOException $exception) {
			$this->processPdoException($exception);
			throw $exception;
		} finally {
			$this->fire('db.afterCommit', $nesting, $this->transactionLevel);
		}
	}

	/**
	 * Rollback the current transaction or to a savepoint if nesting is enabled and supported by the driver.
	 * @param bool $nesting Whether to use savepoints for nested transactions (if supported by the driver)
	 * @return bool|int
	 * @throws \Exception
	 */
	public function rollback(bool $nesting = true): bool|int
	{
		if ($this->transactionLevel === 0) {
			throw new RuntimeException(
				"There is no active transaction"
			);
		}
		try {
			$this->fire('db.beforeRollback', $nesting, $this->transactionLevel);
			$level = $this->transactionLevel--;
			if ($level === 1) {
				$result = $this->pdo->rollBack();
			} elseif ($nesting) {
				switch ($this->driverName) {
					case 'mysql':
					case 'pgsql':
					case 'sqlite':
						$result = $this->pdo->exec("ROLLBACK TO SAVEPOINT trans$level");
						break;
					default:
						$result = $this->pdo->rollBack();
				}
			} else {
				return false;
			}
			if ($result === false) {
				$info          = $this->pdo->errorInfo();
				$ex            = new PDOException($info[2] ?? 'Unknown error');
				$ex->errorInfo = $info;
				throw $ex;
			}
			return $result;
		} catch (PDOException $exception) {
			$this->processPdoException($exception);
			throw $exception;
		} finally {
			$this->fire('db.afterRollback', $nesting, $this->transactionLevel);
		}
	}

	/**
	 * Quote a string for use in a query.
	 * @param ?string $str
	 * @return string
	 */
	public function quote(?string $str): bool|string
	{
		if ($str === null) {
			return 'NULL';
		} else {
			return $this->pdo->quote($str);
		}
	}

	/**
	 * Quote one or more identifier parts (schema, table, column) using the driver-appropriate quote character.
	 * Parts are joined with a dot separator. NULL parts are skipped. "*" is passed through unquoted.
	 * @param string|null ...$args Identifier parts to quote and join (e.g. schema, table, column).
	 * @return string Fully quoted identifier string.
	 */
	public function quoteIdentifier(?string ...$args): string
	{
		$quoted = '';
		$sep    = '';
		foreach ($args as $arg) {
			if ($arg === null) {
				continue;
			}
			$quoted .= $sep;
			if ($arg === '*') {
				$quoted .= '*';
			} else {
				$quoted .= $this->quoteChar;
				$quoted .= str_replace(
					$this->quoteChar,
					$this->quoteChar . $this->quoteChar,
					$arg
				);
				$quoted .= $this->quoteChar;
			}
			$sep = '.';
		}
		return $quoted;
	}

	/**
	 * Return the underlying PDO connection instance.
	 * @return PDO|null The PDO instance, or null if not connected.
	 */
	public function getInternalConnection(): ?PDO
	{
		return $this->pdo;
	}

	/**
	 * Create a new Query builder instance associated with this database connection.
	 * @return Query
	 */
	public function builder(): Query
	{
		return new Query($this);
	}

	/**
	 * Return the lowercase database driver name extracted from the DSN (e.g. "mysql", "pgsql", "sqlite").
	 * @return string Driver name.
	 */
	public function getDriver(): string
	{
		return $this->driverName;
	}
}