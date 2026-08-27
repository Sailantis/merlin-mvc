<?php

namespace Azera\Db;

use Azera\Core\Model;
use PDO;
use PDOStatement;

/**
 * @template TModel of Model
 */
class ResultSet implements \Iterator, \Countable
{
	protected Database $db;
	protected PDOStatement $statement;
	protected ?string $sqlStatement;
	protected ?array $boundParams;
	protected int $fetchMode;

	/** @var class-string<TModel> */
	protected ?string $modelClass;
	protected mixed $firstModel = null;

	// Iterator state
	protected mixed $currentRow = null;
	protected int $position = 0;
	protected bool $initialized = false;

	/** @var bool Whether this result set stems from a read-only (SELECT) statement. */
	protected bool $isReadQuery;

	/**
	 * Create a new ResultSet wrapping a PDO statement result.
	 *
	 * @param Database        $connection   Database connection used to execute the query.
	 * @param PDOStatement    $statement    The executed PDO statement.
	 * @param string|null     $sqlStatement The original SQL string (used by reexecute()).
	 * @param array|null      $boundParams  Bound parameters (used by reexecute()).
	 * @param class-string|null $modelClass Optional model class name used for hydration (sets the fetch class).
	 * @param bool            $isReadQuery Whether the statement is a read-only SELECT. Defaults to true.
	 *                          Set to false for write statements (e.g. INSERT/UPDATE/DELETE ... RETURNING),
	 *                          which cannot be safely re-executed via {@see refresh()}.
	 */
	public function __construct(
		Database $connection,
		PDOStatement $statement,
		?string $sqlStatement = null,
		?array $boundParams = null,
		?string $modelClass = null,
		bool $isReadQuery = true
	) {
		$this->db           = $connection;
		$this->statement    = $statement;
		$this->sqlStatement = $sqlStatement;
		$this->boundParams  = $boundParams;
		$this->modelClass   = $modelClass;
		$this->isReadQuery  = $isReadQuery;

		$this->fetchMode = $connection->getInternalConnection()
			->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);
	}

	/**
	 * Fetch next row as object or array depending on fetch mode.
	 * @return object|array|false The next row as an object or array depending on the fetch mode, or false if there are no more rows.
	 */
	public function fetch(): object|array|false
	{
		$this->position++;
		return $this->statement->fetch($this->fetchMode);
	}

	/**
	 * Fetch next row as associative array.
	 * @return array|false The next row as an associative array, or false if there are no more rows.
	 */
	public function fetchAssoc(): array|false
	{
		$this->position++;
		return $this->statement->fetch(PDO::FETCH_ASSOC);
	}

	/**
	 * Fetch next row as object.
	 * @return object|false The next row as an object, or false if there are no more rows.
	 */
	public function fetchObject(): object|false
	{
		$this->position++;
		return $this->statement->fetch(PDO::FETCH_OBJ);
	}

	/**
	 * Fetch next row as a single column value.
	 * @param int $column Zero-based column index to fetch, or 0 for the first column.
	 * @return mixed The value of the specified column in the next row, or false if there are no more rows.
	 */
	public function fetchColumn(int $column = 0): mixed
	{
		$this->position++;
		return $this->statement->fetchColumn($column);
	}

	/**
	 * Return all rows as associative array.
	 * @return array<int, array<string, mixed>> An array of all remaining rows, each as an associative array.
	 */
	public function fetchAllAssoc(): array
	{
		$result = $this->statement->fetchAll(PDO::FETCH_ASSOC);
		$this->position += \count($result);
		return $result;
	}

	/**
	 * Return all rows as object.
	 * @return array<int, object> An array of all remaining rows, each as an object.
	 */
	public function fetchAllObject(): array
	{
		$result = $this->statement->fetchAll(PDO::FETCH_OBJ);
		$this->position += \count($result);
		return $result;
	}

	/**
	 * Fetch all values from a single column.
	 * @param int $column Zero-based column index to fetch, or 0 for the first column.
	 * @return array The values of the specified column in all remaining rows.
	 */
	public function fetchAllColumn(int $column = 0): array
	{
		$result = $this->statement->fetchAll(PDO::FETCH_COLUMN, $column);
		$this->position += \count($result);
		return $result;
	}

	/**
	 * Fetch all rows as objects or arrays depending on fetch mode.
	 * @param int $fetchMode PDO::FETCH_* constant or 0 for default fetch mode
	 * @return array An array of all remaining rows, each as an object or array depending on the fetch mode.
	 */
	public function fetchAll(int $fetchMode = PDO::FETCH_DEFAULT): array
	{
		$result = $this->statement->fetchAll($fetchMode ?: $this->fetchMode);
		$this->position += \count($result);
		return $result;
	}

	/**
	 * Set the default fetch mode for this result set.
	 * @param int $fetchMode One of the PDO::FETCH_* constants
	 */
	public function setFetchMode(int $fetchMode): void
	{
		$this->fetchMode = $fetchMode;
	}

	/**
	 * Get the next model from the result set, or false if there are no more models. This method will attempt to hydrate a model if a model class was provided when the ResultSet was created. If no model class was provided, it will return false.
	 * @return TModel|null The next model instance, or null if there are no more models.
	 */
	public function nextModel(): ?Model
	{
		// If no model is available, model hydration is impossible
		if (!$this->modelClass) {
			return null;
		}

		// Hydrate via PDO
		$this->statement->setFetchMode(
			PDO::FETCH_CLASS,
			$this->modelClass
		);

		$model = $this->statement->fetch();

		if ($model === false) {
			return null;
		}

		// Save state for ORM
		if ($model instanceof Model) {
			$model->saveState();
		}

		// Cache first model if not cached yet
		if ($this->firstModel === null) {
			$this->firstModel = $model;
		}

		$this->position++;
		return $model;
	}

	/**
	 * Get first model or object from result set.
	 * @return TModel|null The first model instance, or null if there are no models or if the first row cannot be hydrated as a model.
	 */
	public function firstModel(): ?Model
	{
		// If already cached, return cached model
		if ($this->firstModel !== null) {
			return ($this->firstModel instanceof Model) ? $this->firstModel : null;
		}
		// If no model available, we cannot hydrate
		if (!$this->modelClass) {
			return null;
		}
		// If cursor already moved, we cannot reliably return the first model
		if ($this->position > 0) {
			return null;
		}
		// Fetch first model
		return $this->nextModel();
	}

	/**
	 * Get all remaining rows hydrated as model instances.
	 *
	 * Calls {@see nextModel()} repeatedly until the result set is exhausted.
	 * Returns an empty array when no model class was provided at construction.
	 *
	 * @return array<int, TModel> An array of all remaining model instances, or an empty array if there are no more models.
	 */
	public function allModels(): array
	{
		// If no model available, we cannot hydrate
		if (!$this->modelClass) {
			return [];
		}
		// Fetch all models until no more are available
		$models = [];
		while ($model = $this->nextModel()) {
			$models[] = $model;
		}
		return $models;
	}

	/**
	 * Return the SQL statement that was executed to produce this result set, if available.
	 * @return string|null The SQL statement string, or null if not available.
	 */
	public function getSql(): ?string
	{
		return $this->sqlStatement;
	}

	/**
	 * Return the variables that were bound to the SQL statement, if available.
	 * @return array|null The variables that were bound to the SQL statement, or null if not available.
	 */
	public function getBindings(): ?array
	{
		return $this->boundParams;
	}

	/**
	 * Convert the result set to a plain array of rows.
	 *
	 * Each row is cast to an associative array (via castToArray on model
	 * instances, or fetched as assoc from PDO for plain rows).  This makes
	 * the result set compatible with template engines and serializers that
	 * expect array-like data (e.g. Clarity's castToArray).
	 *
	 * @return array<int, array<string, mixed>> All remaining rows as arrays.
	 */
	public function toArray(): array
	{
		if ($this->modelClass) {
			$rows = [];
			while ($model = $this->nextModel()) {
				$row = [];
				foreach (\get_object_vars($model) as $key => $val) {
					// Skip internal model properties (prefixed with __)
					if (\str_starts_with($key, '__')) {
						continue;
					}
					$row[$key] = $val;
				}
				$rows[] = $row;
			}
			return $rows;
		}

		return $this->fetchAllAssoc();
	}

	/**
	 * Execute the query again to repopulate the result set.
	 *
	 * Only read-only (SELECT) result sets can be safely refreshed. Refreshing a
	 * write statement (e.g. INSERT/UPDATE/DELETE ... RETURNING) would re-execute
	 * the write, so it is rejected.
	 *
	 * @throws Exception If this result set does not originate from a SELECT statement.
	 * @return void
	 */
	public function refresh(): void
	{
		if (!$this->isReadQuery) {
			throw new Exception('Cannot refresh: statement is not a read-only SELECT result set.');
		}
		$this->closeCursor();
		$stmt = $this->db->query(
			$this->sqlStatement,
			$this->boundParams
		);
		$this->statement   = $stmt;
		$this->currentRow  = null;
		$this->position    = 0;
		$this->initialized = false;
		$this->firstModel  = null;
	}

	// Iterator methods

	/**
	 * Rewind the iterator to the first row.
	 *
	 * The underlying PDOStatement cursor is forward-only, so we cannot
	 * actually rewind once iteration has started.  However, on the first
	 * call (before any rows have been fetched) we lazily fetch the first
	 * row so that valid() returns true and PHP's foreach can begin.
	 */
	public function rewind(): void
	{
		if (!$this->initialized) {
			$this->currentRow  = $this->fetch();
			$this->initialized = true;
		}
	}

	/** Return the current row (fetched lazily on first access). */
	public function current(): mixed
	{
		if (!$this->initialized) {
			$this->currentRow  = $this->fetch();
			$this->initialized = true;
		}
		return $this->currentRow;
	}

	/** Return the zero-based position of the current row within this traversal. */
	public function key(): int
	{
		return $this->position;
	}

	/** Advance to the next row. */
	public function next(): void
	{
		$this->currentRow = $this->fetch();
		$this->position++;
	}

	/** Return true while the current row is not false/null (i.e., while rows remain). */
	public function valid(): bool
	{
		return $this->currentRow !== false && $this->currentRow !== null;
	}

	/**
	 * Return the number of rows affected/returned by the underlying statement.
	 * @return int Row count as reported by PDOStatement::rowCount().
	 */
	public function count(): int
	{
		return $this->statement->rowCount();
	}

	/**
	 * Close the cursor on the underlying PDO statement.
	 *
	 * This releases any locks the statement may still hold.  It is especially
	 * important for statements that only partially consumed their result set
	 * (e.g. {@see Model::__performWrite()} fetching a single RETURNING row): on
	 * SQLite in WAL mode, an open cursor on a write statement keeps the write
	 * lock held on its connection, blocking writes from other connections.
	 *
	 * @return void
	 */
	public function closeCursor(): void
	{
		$this->statement->closeCursor();
	}

	/**
	 * Ensure the underlying statement cursor is released when the result set
	 * goes out of scope, so that any database locks it holds are freed.
	 */
	public function __destruct()
	{
		$this->closeCursor();
	}

}