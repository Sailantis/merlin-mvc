<?php

namespace Merlin\Db;

use LogicException;
use Merlin\AppContext;
use Merlin\Mvc\Model;
use Merlin\Mvc\ModelMapping;
use PDOStatement;

/**
 * Unified query builder for SELECT, INSERT, UPDATE, DELETE operations
 * 
 * @example
 * // SELECT
 * $users = Query::new()->table('users')->where('active', 1)->select();
 * $user = Query::new()->table('users')->where('id', 5)->first();
 * 
 * // INSERT
 * Query::new()->table('users')->insert(['name' => 'John', 'email' => 'john@example.com']);
 * 
 * // UPSERT with ON CONFLICT/ON DUPLICATE KEY UPDATE
 * Query::new()->table('users')->upsert(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
 * 
 * // UPDATE
 * Query::new()->table('users')->where('id', 5)->update(['name' => 'Jane']);
 * 
 * // DELETE
 * Query::new()->table('users')->where('id', 5)->delete();
 * 
 * // EXISTS / COUNT
 * $exists = Query::new()->table('users')->where('email', 'test@example.com')->exists();
 * $count = Query::new()->table('users')->where('active', 1)->count();
 */
class Query extends Condition
{
    /* -------------------------------------------------------------
     *  STATIC MODEL RESOLUTION
     * ------------------------------------------------------------- */

    /**
     * @var bool
     */
    protected static bool $useModels = true;

    /**
     * @var Model[]
     */
    protected static array $modelCache = [];

    protected static ?ModelMapping $modelMapping = null;

    /**
     * Enable or disable automatic model resolution for queries. If enabled, the query will resolve table names and database connections from model classes. If disabled, the query will treat table names as literal and use database connections from AppContext. This can be useful for simple queries or when you want to avoid coupling to model classes.
     * @param bool $useModels
     */
    public static function useModels(bool $useModels): void
    {
        self::$useModels = $useModels;
    }

    /**
     * Set the model mapping instance to use for resolving model class names to table names and database connections. This can be used instead of model classes for simple queries or when you want to avoid coupling to model classes.
     * @param ModelMapping|null $modelMapping
     */
    public static function setModelMapping(?ModelMapping $modelMapping): void
    {
        self::$modelMapping = $modelMapping;
    }

    /**
     * Get model instance by name, using cache to avoid multiple instantiations. Throws exception if model class does not exist or is not a subclass of Model.
     * @param string $modelName
     * @return Model
     * @throws LogicException
     */
    protected static function getModel(string $modelName): Model
    {
        if (!isset(self::$modelCache[$modelName])) {
            if (!class_exists($modelName)) {
                throw new LogicException('Undefined model "' . $modelName . '"');
            }
            if (!is_subclass_of($modelName, Model::class)) {
                throw new LogicException('Class "' . $modelName . '" is not a valid model');
            }
            self::$modelCache[$modelName] = new $modelName();
        }
        return self::$modelCache[$modelName];
    }

    /* -------------------------------------------------------------
     *  INSTANCE PROPERTIES
     * ------------------------------------------------------------- */

    protected ?Model $model;

    protected array $manualBindings = [];

    protected int $limit;

    protected int $offset;

    protected int $rowCount;

    protected bool $isReadQuery = true;

    protected bool $forceSelect = false;

    protected ?array $columns;

    protected array $joins;

    protected array $orderBy;

    protected array $values;

    protected ?string $table = null;

    protected bool $returnSql = false;

    /* -------------------------------------------------------------
     *  SELECT-SPECIFIC PROPERTIES
     * ------------------------------------------------------------- */

    protected array $groupBy;

    protected bool $forUpdate;

    protected bool $sharedLock;

    protected bool $distinct;

    protected string $preColumnInjection;

    /* -------------------------------------------------------------
     *  INSERT-SPECIFIC PROPERTIES
     * ------------------------------------------------------------- */

    protected bool $replaceInto = false;

    protected bool $ignore = false;

    protected array $updateValues = [];

    protected bool $updateValuesIsList = false;

    protected array|string $conflictTarget = '';

    protected array|string|null $returning = null;

    /* -------------------------------------------------------------
     *  CONSTRUCTOR & FACTORY
     * ------------------------------------------------------------- */

    /**
     * Constructor. Can optionally pass a Database connection to use for this query, or a Model to automatically set the table and connection.
     * @param Database|null $db
     * @param Model|null $model
     */
    public function __construct(?Database $db = null, ?Model $model = null)
    {
        parent::__construct($db);
        $this->model = $model;
    }

    /**
     * Factory method to create a new Query instance. Can optionally pass a Database connection to use for this query.
     * @param Database|null $db
     * @return static
     */
    public static function new(?Database $db = null): static
    {
        return new static($db);
    }

    /**
     * Get the database connection to use for this query, either from the model or from the AppContext if no connection is set on the query or model
     * @return Database
     * @throws Exception
     */
    protected function getDb(): Database
    {
        if ($this->db !== null) {
            return $this->db;
        }

        if ($this->model !== null) {
            return $this->isReadQuery
                ? $this->model->readConnection()
                : $this->model->writeConnection();
        }

        $role = $this->isReadQuery ? 'read' : 'write';
        return AppContext::instance()->dbManager()->getOrDefault($role);
    }

    /* -------------------------------------------------------------
     *  TABLE SETUP
     * ------------------------------------------------------------- */

    /**
     * Set the table for this query. Can be either a table name or a model class name. If a model class name is provided, the corresponding table will be used and the model's database connection will be used if no connection is set on the query.
     * @param string $name Table name or model class name
     * @param string|null $alias Optional table alias
     * @return $this
     * @throws Exception
     */
    public function table(string $name, ?string $alias = null): static
    {
        if (self::$useModels && !isset(self::$modelMapping)) {
			$this->model = self::getModel($name);
        } else {
    		$this->model = null;
        }
        $this->table = $this->protectIdentifier($name, self::PI_TABLE, $alias);
        $this->forceSelect = false;
        return $this;
    }

    /**
     * Set the source for this query from a subquery or raw table expression. The subquery will be wrapped in parentheses and treated as a table. An optional alias can be provided for the subquery.
     * @param string|Query $source Subquery or raw table expression
     * @param string|null $alias Optional alias for the subquery
     * @return $this
     * @throws Exception
     */
    public function from(string|Query $source, ?string $alias = null): static
    {
        $this->model = null;

        if ($source instanceof Query) {
            $sql = '(' . $source->toSql() . ')';
            if ($alias) {
                $sql .= ' AS ' . $this->quoteIdentifier($alias);
            }
            $this->subQueryBindings = $source->getBindings() + $this->subQueryBindings;
            $this->forceSelect = true; // Force SELECT mode for subqueries
        } else {
            if (self::$useModels && !isset(self::$modelMapping)) {
                $this->model = self::getModel($source);
            }
            $sql = $this->protectIdentifier($source, self::PI_TABLE, $alias);
            $this->forceSelect = false;
        }

        $this->table = $sql;

        return $this;
    }

    /* -------------------------------------------------------------
     *  FLUENT METHODS (SHARED)
     * ------------------------------------------------------------- */

    /**
     * Set columns for SELECT queries. Can be either a comma-separated string or an array of column names.
     * @param string|array $columns
     * @return $this
     */
    public function columns(string|array $columns): static
    {
        if (!empty($columns)) {
            $this->columns = \is_array($columns) ? $columns : explode(',', $columns);
        } else {
            $this->columns = null;
        }
        return $this;
    }

    /**
     * Set the LIMIT and optional OFFSET for SELECT queries
     * (or limit number of rows affected for UPDATE/DELETE)
     * @param int $limit Number of rows to limit
     * @param int|null $offset Optional offset for the limit
     * @return $this
     */
    public function limit(int $limit, ?int $offset = null): static
    {
        $this->limit = $limit;
        if ($offset !== null) {
            $this->offset = $offset;
        }
        return $this;
    }

    /**
     * Sets an OFFSET clause for SELECT queries
     * @param int $offset Number of rows to offset
     * @return $this
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Adds values for INSERT or UPDATE queries. Can be either:
     * - An associative array of column => value pairs
     * - An object with public properties
     * @param array|object $values
     * @param bool $escape
     * @return $this
     */
    public function values(array|object $values, bool $escape = true): static
    {
        $values = \is_object($values)
            ? \get_object_vars($values) : $values;

        if ($escape) {
            foreach ($values as $index => $value) {
                // Sql instances are stored as-is, serialized later
                if (!($value instanceof Sql)) {
                    $values[$index] = $this->escapeValue($value);
                }
            }
        }
        $this->values[] = $values;
        return $this;
    }

    /**
     * Set multiple rows of values for bulk insert operations.
     * Each item in the list should be an array of column => value pairs.
     * @param array $valuesList
     * @param bool $escape
     * @return $this
     */
    public function bulkValues(array $valuesList = [], bool $escape = true): static
    {
        if ($escape) {
            foreach ($valuesList as $index => $values) {
                foreach ($values as $key => $value) {
                    // Sql instances are stored as-is, serialized later
                    if (!($value instanceof Sql)) {
                        $valuesList[$index][$key] = $this->escapeValue($value);
                    }
                }
            }
        }
        $this->values = array_values($valuesList);
        return $this;
    }

    /**
     * Check if any values have been set for this query
     * @return bool
     */
    public function hasValues(): bool
    {
        return !empty($this->values[0]);
    }

    /**
     * Set a value for INSERT or UPDATE queries. Can be either:
     * - A single column name and value pair
     * - An associative array of column => value pairs
     * @param string|array $column
     * @param mixed $value
     * @param bool $escape
     * @return $this
     */
    public function set(string|array $column, mixed $value = null, bool $escape = true): static
    {
        if (!isset($this->values[0])) {
            $this->values[] = [];
        }
        $index = count($this->values) - 1;
        if (\is_array($column)) {
            foreach ($column as $sKey => $value) {
                if ($escape && !($value instanceof Sql)) {
                    $value = $this->escapeValue($value);
                }
                $this->values[$index][$sKey] = $value;
            }
        } else {
            if ($escape && !($value instanceof Sql)) {
                $value = $this->escapeValue($value);
            }
            $this->values[$index][$column] = $value;
        }
        return $this;
    }

    /**
     * Adds an INNER join to the query
     * @param string|Query $model
     * @param string|Condition|null $alias
     * @param string|Condition|null $conditions
     * @return $this
     * @throws Exception
     */
    public function innerJoin(string|Query $model, string|Condition|null $alias = null, string|Condition|null $conditions = null): static
    {
        return $this->join($model, $alias, $conditions, 'INNER');
    }

    /**
     * Adds a LEFT join to the query
     * @param string|Query $model
     * @param string|Condition|null $alias
     * @param string|Condition|null $conditions
     * @return $this
     * @throws Exception
     */
    public function leftJoin(string|Query $model, string|Condition|null $alias = null, string|Condition|null $conditions = null): static
    {
        return $this->join($model, $alias, $conditions, 'LEFT');
    }

    /**
     * Adds a RIGHT join to the query
     * @param string|Query $model
     * @param string|Condition|null $alias
     * @param string|Condition|null $conditions
     * @return $this
     * @throws Exception
     */
    public function rightJoin(string|Query $model, string|Condition|null $alias = null, string|Condition|null $conditions = null): static
    {
        return $this->join($model, $alias, $conditions, 'RIGHT');
    }

    /**
     * Adds a CROSS join to the query
     * @param string|Query $model
     * @param string|Condition|null $alias
     * @param string|Condition|null $conditions
     * @return $this
     * @throws Exception
     */
    public function crossJoin(string|Query $model, string|Condition|null $alias = null, string|Condition|null $conditions = null): static
    {
        return $this->join($model, $alias, $conditions, 'CROSS');
    }

    /**
     * Add a JOIN clause to the query
     * @param string|Query $model
     * @param string|Condition|null $alias
     * @param string|Condition|null $conditions
     * @param string|null $type
     * @return $this
     * @throws Exception
     */
    public function join(string|Query $model, string|Condition|null $alias = null, string|Condition|null $conditions = null, ?string $type = null): static
    {
        if ($model instanceof Query) {
            $this->subQueryBindings = $model->getBindings() + $this->subQueryBindings;
            $model = '(' . $model->toSql() . ')';
            $pos = false;
        } else {
            $pos = strrpos($model, ' ');
        }
        if ($pos !== false) {
            // If conditions parameter is not provided, treat the second
            // part as alias
            if ($conditions === null && $alias !== null) {
                $conditions = $alias;
                $alias = null;
            }

            if ($alias === null) {
                $alias = substr($model, $pos + 1);
                $model = substr($model, 0, $pos);
            }

        } elseif ($alias instanceof Condition) {
            $conditions = $alias;
            $alias = null;

        } elseif (
            \is_string($alias) &&
            preg_match('/[=<>!]| LIKE | IN | IS | BETWEEN /i', $alias)
        ) {
            $conditions = $alias;
            $alias = null;

        } else {
            if ($conditions === null) {
                $conditions = '';
            }
        }

        // Register table in cache before compiling join conditions to allow 
        // referencing the alias in conditions
        $table = $this->getFullTableName($model, is_string($alias) ? $alias : null);

        if (!isset($this->joins)) {
            $this->joins = [];
        }

        $this->joins[] = [
            'table' => $table,
            'conditions' => $conditions,
            'type' => $type,
        ];

        return $this;
    }

    /**
     * Set ORDER BY clause
     * @param array|string $orderBy
     * @return $this
     */
    public function orderBy(array|string $orderBy): static
    {
        $this->orderBy = \is_string($orderBy)
            ? explode(',', $orderBy)
            : $orderBy;
        return $this;
    }

    /**
     * Bind parameters for prepared statements. Can be either an associative array or an object with properties as parameter names.
     * @param array|object $bindParams
     * @return $this
     */
    public function bind(array|object $bindParams): static
    {
        $this->manualBindings = \is_object($bindParams)
            ? \get_object_vars($bindParams)
            : $bindParams;

        return $this;
    }

    /**
     * Set whether to return the SQL string instead of executing the query
     * @param bool $returnSql
     * @return $this
     */
    public function returnSql(bool $returnSql = true): static
    {
        $this->returnSql = $returnSql;
        return $this;
    }

    /* -------------------------------------------------------------
     *  SELECT-SPECIFIC METHODS
     * ------------------------------------------------------------- */

    /**
     * Set DISTINCT modifier for SELECT queries
     * @param bool $distinct
     * @return $this
     */
    public function distinct(bool $distinct): static
    {
        $this->distinct = $distinct;
        return $this;
    }

    /**
     * Set a string to be injected before the column list in SELECT queries (e.g. for SQL_CALC_FOUND_ROWS in MySQL)
     * @param string $inject
     * @return $this
     */
    public function injectBeforeColumns(string $inject): static
    {
        $this->preColumnInjection = $inject;
        return $this;
    }

    /**
     * Set GROUP BY clause
     * @param array|string $groupBy
     * @return $this
     */
    public function groupBy(array|string $groupBy): static
    {
        $this->groupBy = \is_string($groupBy)
            ? explode(',', $groupBy)
            : $groupBy;
        return $this;
    }

    /**
     * Sets a FOR UPDATE clause (MySQL/PostgreSQL) or FOR SHARE (PostgreSQL)
     * @param bool $forUpdate
     * @return $this
     */
    public function forUpdate(bool $forUpdate): static
    {
        $this->forUpdate = $forUpdate;
        return $this;
    }

    /**
     * Sets a LOCK IN SHARE MODE / FOR SHARE clause (MySQL/PostgreSQL)
     * @param bool $sharedLock
     * @return $this
     */
    public function sharedLock(bool $sharedLock): static
    {
        $this->sharedLock = $sharedLock;
        return $this;
    }

    /* -------------------------------------------------------------
     *  INSERT-SPECIFIC METHODS
     * ------------------------------------------------------------- */

    /**
     * Mark this as a REPLACE INTO operation (MySQL/SQLite)
     * @param bool $replace
     * @return $this
     */
    public function replace(bool $replace = true): static
    {
        $this->replaceInto = $replace;
        return $this;
    }

    /**
     * Set IGNORE modifier for INSERT (MySQL/SQLite) or ON CONFLICT DO NOTHING (PostgreSQL)
     * @param bool $ignore
     * @return $this
     */
    public function ignore(bool $ignore = true): static
    {
        $this->ignore = $ignore;
        return $this;
    }

    /**
     * Set values for ON CONFLICT/ON DUPLICATE KEY UPDATE clause. Can be either:
     * - List array -> EXCLUDED/VALUES mode
     * - Assoc array -> explicit values
     * @param array $updateValues
     * @param bool $escape
     * @return $this
     */
    public function updateValues(array $updateValues, bool $escape = true): static
    {
        // List array -> EXCLUDED/VALUES mode
        if (isset($updateValues[0])) {
            // Assume values are column names, convert to column => EXCLUDED/VALUES(column) pairs
            $this->updateValues = $updateValues;
            $this->updateValuesIsList = true;
            return $this;
        }

        // Assoc array -> explicit values
        if ($escape) {
            foreach ($updateValues as $column => $value) {
                if (!($value instanceof Sql)) {
                    $updateValues[$column] = $this->escapeValue($value);
                }
            }
        }

        $this->updateValues = $updateValues;
        $this->updateValuesIsList = false;
        return $this;
    }

    /**
     * Set conflict target for ON CONFLICT clause (PostgreSQL). Can be either:
     * - Array with column names
     * - String with column names or constraint name
     * @param array|string $columnsOrConstraint
     * @return $this
     */
    public function conflict(array|string $columnsOrConstraint): static
    {
        $this->conflictTarget = $columnsOrConstraint;
        return $this;
    }

    /**
     * Set columns to return from an INSERT/UPDATE/DELETE query. Supported by PostgreSQL (RETURNING) and MySQL (RETURNING with MySQL 8.0.27+)
     * @param array|string|null $columns
     * @return $this
     * @throws Exception
     */
    public function returning(array|string|null $columns): static
    {
        if (!empty($columns)) {
            $this->returning = is_array($columns)
                ? $columns
                : explode(',', $columns);
        } else {
            $this->returning = null;
        }
        return $this;
    }

    /* -------------------------------------------------------------
     *  END OPERATIONS
     * ------------------------------------------------------------- */

    /**
     * Compile and return the SQL string for this query without executing it
     * @return string
     * @throws Exception
     */
    public function toSql(): string
    {
        $this->isReadQuery = true;
        $db = $this->getDb();
        $query = $this->compileSelect($db);
        return $this->prepareQueryForReturn($query);
    }

    /**
     * Execute SELECT query and return ResultSet or return SQL string if returnSql is enabled
     * @param array|string|null $columns Columns to select, or null to ignore parameter. Can be either a comma-separated string or an array of column names.
     * @return ResultSet|string
     * @throws Exception
     */
    public function select(array|string|null $columns = null): ResultSet|string
    {
        $this->isReadQuery = true;
        $db = $this->getDb();

        if ($columns !== null) {
            $this->columns($columns);
        }
        $query = $this->compileSelect($db);
        if ($this->returnSql) {
            return $this->prepareQueryForReturn($query);
        }
        $result = $this->executeQuery($db, $query);
        if (!($result instanceof ResultSet)) {
            throw new Exception('SELECT query did not return ResultSet');
        }

        return $result;
    }

    /**
     * Execute SELECT query and return first model or null or return SQL string if returnSql is enabled
     * @return Model|string|null First model, or SQL string, or null if no results
     * @throws Exception
     */
    public function first(): Model|string|null
    {
        $result = $this->limit(1)->select();
        if ($this->returnSql) {
            return $result;
        }
        return $result->firstModel();
    }

    /**
     * Execute INSERT or UPSERT query or return SQL string if returnSql is enabled
     * @param array|null $data Data to insert
     * @return bool|string|array|ResultSet Insert ID, true on success, or SQL string, or result of returning clause
     * @throws Exception
     */
    public function insert(?array $data = null): bool|string|array|ResultSet
    {
        return $this->runInsert($data, !empty($this->updateValues));
    }

    /**
     * Execute UPSERT query (INSERT with ON CONFLICT/ON DUPLICATE KEY UPDATE) or return SQL string if returnSql is enabled
     * @param array|null $data Data to insert
     * @return bool|string|array|ResultSet Insert ID, true on success, or SQL string, or result of returning clause
     * @throws Exception
     */
    public function upsert(?array $data = null): bool|string|array|ResultSet
    {
        return $this->runInsert($data, true);
    }

    protected function runInsert(?array $data, bool $upsert): bool|string|array|ResultSet
    {
        $this->isReadQuery = false;
        $db = $this->getDb();

        // Set values if data provided
        if (!empty($data)) {
            $this->values($data);
        }

        $query = $this->compileInsert($db, $upsert);

        if ($this->returnSql) {
            return $this->prepareQueryForReturn($query);
        }

        $result = $this->executeQuery($db, $query);

        // Return insert ID for single inserts
        if ($result instanceof ResultSet) {
            return $result;
        }

        // For non-RETURNING queries, try to get last insert ID
        $lastId = $db->getInternalConnection()->lastInsertId();
        return $lastId ?: true;
    }

    /**
     * Execute UPDATE query or return SQL string if returnSql is enabled
     * @param ?array $data Data to update
     * @return int|string|array|ResultSet Number of affected rows or SQL string, or row of returning clause
     * @throws Exception
     */
    public function update(?array $data = null): int|string|array|ResultSet
    {
        $this->isReadQuery = false;
        $db = $this->getDb();

        // Set values if data provided
        if (!empty($data)) {
            $this->values($data);
        }

        $query = $this->compileUpdate($db);

        if ($this->returnSql) {
            return $this->prepareQueryForReturn($query);
        }

        $result = $this->executeQuery($db, $query);

        if ($result instanceof ResultSet) {
            return $result;
        }

        return $this->rowCount;
    }

    /**
     * Execute DELETE query
     * @return int|string|array|ResultSet Number of affected rows, SQL string, or result of returning clause
     * @throws Exception
     */
    public function delete(): int|string|array|ResultSet
    {
        $this->isReadQuery = false;
        $db = $this->getDb();

        $query = $this->compileDelete($db);

        if ($this->returnSql) {
            return $this->prepareQueryForReturn($query);
        }

        $result = $this->executeQuery($db, $query);

        if ($result instanceof ResultSet) {
            return $result;
        }

        return $this->rowCount;
    }

    /**
     * Execute TRUNCATE query or return SQL string if returnSql is enabled
     * @return int|string Number of affected rows or SQL string
     * @throws Exception
     */
    public function truncate(): int|string
    {
        $this->isReadQuery = false;
        $db = $this->getDb();

        $query = $this->compileTruncate($db);

        if ($this->returnSql) {
            return $this->prepareQueryForReturn($query);
        }

        $this->executeQuery($db, $query);

        return $this->rowCount;
    }

    /**
     * Check if any rows exist matching the query
     * @return bool|string
     * @throws Exception
     */
    public function exists(): bool|string
    {
        $this->isReadQuery = true;
        $db = $this->getDb();

        $query = $this->compileExists($db);

        if ($this->returnSql) {
            return $this->prepareQueryForReturn($query);
        }

        $result = $this->executeQuery($db, $query);

        if (empty($result)) {
            return false;
        }

        $exists = $result->fetchColumn();

        return !empty($exists);
    }

    /**
     * Count rows matching the query
     * @return int|string Number of matching rows or SQL string
     * @throws Exception
     */
    public function count(): int|string
    {
        $this->isReadQuery = true;
        $db = $this->getDb();

        $query = $this->compileCount($db);

        if ($this->returnSql) {
            return $this->prepareQueryForReturn($query);
        }

        $result = $this->executeQuery($db, $query);

        if (empty($result)) {
            return 0;
        }

        return (int) ($result->fetchColumn() ?? 0);
    }

    /* -------------------------------------------------------------
     *  SQL COMPILATION
     * ------------------------------------------------------------- */

    /**
     * Compile SELECT statement
     * @return string
     * @throws LogicException
     */
    protected function compileSelect(Database $db): string
    {
        if (empty($this->table)) {
            throw new LogicException('No table set for SELECT query');
        }

        $statement = 'SELECT ';
        if (!empty($this->distinct)) {
            $statement .= ' DISTINCT ';
        }
        if (!empty($this->preColumnInjection)) {
            $statement .= ' ';
            $statement .= $this->preColumnInjection;
            $statement .= ' ';
        }
        if (!empty($this->columns)) {
            $statement .= implode(', ', $this->protectColumns($db, $this->columns));
        } else {
            $statement .= '*';
        }
        $statement .= ' FROM ';
        $statement .= $this->table;

        if (isset($this->joins)) {
            foreach ($this->joins as $join) {
                if (!empty($join['type'])) {
                    $statement .= ' ';
                    $statement .= $join['type'];
                }
                $statement .= ' JOIN ';
                $statement .= $join['table'];
                if (!empty($join['conditions'])) {
                    $statement .= ' ON (';
                    $statement .= $this->compileCondition($join['conditions']);
                    $statement .= ')';
                }
            }
        }
        if (!empty($this->condition)) {
            $statement .= ' WHERE ';
            $statement .= $this->resolveDeferredModels($this->condition);
        }
        if (!empty($this->groupBy)) {
            $sep = '';
            $statement .= ' GROUP BY ';
            foreach ($this->groupBy as $column) {
                $statement .= $sep;
                $statement .= $this->protectIdentifier($column);
                $sep = ',';
            }
        }
        if (!empty($this->orderBy)) {
            $sep = '';
            $statement .= ' ORDER BY ';
            foreach ($this->orderBy as $column) {
                $statement .= $sep;
                $statement .= $this->protectIdentifier($column);
                $sep = ',';
            }
        }
        if (!empty($this->limit)) {
            $statement .= ' LIMIT ' . $this->limit;
            if (!empty($this->offset)) {
                $statement .= ' OFFSET ';
                $statement .= $this->offset;
            }
        }
        if (!empty($this->forUpdate)) {
            $statement .= ' FOR UPDATE';
        } elseif (!empty($this->sharedLock)) {
            switch ($db->getDriver()) {
                case 'mysql':
                    $statement .= ' IN SHARED MODE';
                    break;
                case 'pgsql':
                    $statement .= ' FOR SHARE';
                    break;
                default:
                    throw new LogicException("Shared locks not supported for this driver");
            }
        }
        return $statement;
    }

    /**
     * Compile INSERT statement
     * @param Database $db
     * @param bool $upsert Whether to compile as an UPSERT with ON CONFLICT/ON DUPLICATE KEY UPDATE clause
     * @return string
     * @throws LogicException
     */
    protected function compileInsert(Database $db, bool $upsert = false): string
    {
        if (empty($this->table)) {
            throw new LogicException('No table set for INSERT query');
        }

        // Determine columns from values or bind parameters
        if (empty($this->values)) {
            if (empty($this->manualBindings)) {
                throw new LogicException('No values or bind parameters set');
            }
            $columns = !empty($this->columns)
                ? $this->columns
                : array_keys($this->manualBindings);
        } else {
            $columns = array_keys($this->values[0]);
            if (empty($columns)) {
                throw new LogicException('No columns found in values');
            }
        }

        $driver = $db->getDriver();

        // Determine statement type based on flags and driver capabilities
        if ($this->replaceInto) {
            switch ($driver) {
                case 'mysql':
                    $statement = 'REPLACE INTO ';
                    break;
                case 'sqlite':
                    $statement = 'INSERT OR REPLACE INTO ';
                    break;
                default:
                    throw new LogicException("Replace is not implemented for this driver");
            }
        } elseif ($this->ignore) {
            switch ($driver) {
                case 'mysql':
                    $statement = 'INSERT IGNORE INTO ';
                    break;
                case 'sqlite':
                    $statement = 'INSERT OR IGNORE INTO ';
                    break;
                case 'pgsql':
                    $statement = 'INSERT INTO ';
                    $conflictClause = ' ON CONFLICT DO NOTHING ';
                    break;
                default:
                    throw new LogicException("INSERT IGNORE is not implemented for this driver");
            }
        } else {
            $statement = 'INSERT INTO ';
        }

        // Start building statement
        $statement .= $this->table;
        $statement .= ' (';
        $statement .= implode(',', $this->protectColumns($db, $columns));
        $statement .= ') VALUES ';

        // Use bind parameters if set, otherwise set values directly
        if (!empty($this->manualBindings)) {
            $statement .= '(:';
            $statement .= implode(',:', $columns);
            $statement .= ')';
        } else {
            $rowSep = '';
            foreach ($this->values as $values) {
                $statement .= $rowSep;
                $rowSep = ',';
                $statement .= '(';
                $valSep = '';
                foreach ($values as $value) {
                    $statement .= $valSep;
                    $valSep = ',';
                    if ($value instanceof Sql) {
                        $statement .= $this->serializeScalar($value);
                    } else {
                        $statement .= $value;
                    }
                }
                $statement .= ')';
            }
        }

        // Handle upsert/ON CONFLICT clause if requested
        if ($upsert) {
            switch ($driver) {
                case 'mysql':
                    $statement .= ' ON DUPLICATE KEY UPDATE ';
                    break;
                case 'sqlite':
                    $statement .= ' ON CONFLICT DO UPDATE SET ';
                    break;
                case 'pgsql':
                    if (empty($this->conflictTarget)) {
                        if (isset($this->model)) {
                            $this->conflictTarget = $this->model->idFields();
                            if (empty($this->conflictTarget)) {
                                throw new LogicException(
                                    "PostgreSQL requires a conflict target for UPSERT. No conflict target set and model does not define any ID fields."
                                );
                            }
                        } else {
                            throw new LogicException(
                                "PostgreSQL requires a conflict target for UPSERT"
                            );
                        }
                    }
                    if (is_array($this->conflictTarget)) {
                        $statement .= ' ON CONFLICT (' . implode(',', $this->protectColumns($db, $this->conflictTarget)) . ') DO UPDATE SET ';
                    } else {
                        $statement .= ' ON CONFLICT ON CONSTRAINT ' . $this->protectIdentifier($this->conflictTarget) . ' DO UPDATE SET ';
                    }
                    break;
                default:
                    throw new LogicException("Upsert not implemented for this driver");
            }
            $valSep = '';
            if (!empty($this->updateValues)) {
                if ($this->updateValuesIsList) {
                    switch ($driver) {
                        case 'mysql':
                            $prefix = 'VALUES(';
                            $suffix = ')';
                            break;
                        case 'pgsql':
                        case 'sqlite':
                            $prefix = 'EXCLUDED.';
                            $suffix = '';
                            break;
                        default:
                            throw new LogicException("List-style update values not supported for this driver");
                    }
                    foreach ($this->updateValues as $column) {
                        $statement .= $valSep;
                        $statement .= $this->protectIdentifier($column);
                        $statement .= '=';
                        $statement .= $prefix;
                        $statement .= $this->protectIdentifier($column);
                        $statement .= $suffix;
                        $valSep = ',';
                    }
                } else {
                    foreach ($this->updateValues as $column => $value) {
                        $statement .= $valSep;
                        $statement .= $this->protectIdentifier($column);
                        $statement .= '=';
                        if ($value instanceof Sql) {
                            $statement .= $this->serializeScalar($value);
                        } else {
                            $statement .= $value;
                        }
                        $valSep = ',';
                    }
                }
            } elseif (!empty($this->manualBindings)) {
                foreach ($columns as $column) {
                    $statement .= $valSep;
                    $statement .= $this->protectIdentifier($column);
                    $statement .= '=:';
                    $statement .= $column;
                    $valSep = ',';
                }
            } else {
                if (count($this->values) > 1) {
                    throw new LogicException('Upsert with multiple value sets is not supported without explicit update values');
                }
                foreach ($this->values[0] as $column => $value) {
                    $statement .= $valSep;
                    $statement .= $this->protectIdentifier($column);
                    $statement .= '=';
                    if ($value instanceof Sql) {
                        $statement .= $this->serializeScalar($value);
                    } else {
                        $statement .= $value;
                    }
                    $valSep = ',';
                }
            }
        } elseif (!empty($conflictClause)) {
            $statement .= $conflictClause;
        }

        // Handle RETURNING clause for PostgreSQL
        if (!empty($this->returning)) {
            $statement .= ' RETURNING ';
            $statement .= implode(', ', $this->protectColumns($db, $this->returning));
        }

        return $statement;
    }

    /**
     * Compile UPDATE statement
     * @return string
     * @throws LogicException
     */
    protected function compileUpdate(Database $db): string
    {
        if (empty($this->table)) {
            throw new LogicException('No table set for UPDATE query');
        }

        $statement = 'UPDATE ';
        $statement .= $this->table;

        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                if (!empty($join['type'])) {
                    $statement .= ' ';
                    $statement .= $join['type'];
                }
                $statement .= ' JOIN ';
                $statement .= $join['table'];
                $statement .= ' ON (';
                $statement .= $this->compileCondition($join['conditions']);
                $statement .= ')';
            }
        }

        $statement .= ' SET ';
        if (!empty($this->values[0])) {
            $sep = '';
            foreach ($this->values[0] as $column => $value) {
                $statement .= $sep;
                $statement .= $this->protectIdentifier($column, self::PI_COLUMN);
                $statement .= '=';
                if ($value instanceof Sql) {
                    $statement .= $this->serializeScalar($value);
                } else {
                    $statement .= $value;
                }
                $sep = ',';
            }
        } elseif (!empty($this->columns)) {
            $sep = '';
            foreach ($this->columns as $column) {
                $pos = strpos($column, '=');
                if ($pos > 0) {
                    $value = ltrim(substr($column, $pos + 1));
                    $column = rtrim(substr($column, 0, $pos));
                } else {
                    $value = ":$column";
                }
                $statement .= $sep;
                $statement .= $this->protectIdentifier($column, self::PI_COLUMN);
                $statement .= '=';
                $statement .= $value;
                $sep = ',';
            }
        } elseif (!empty($this->manualBindings)) {
            foreach ($this->manualBindings as $column => $_) {
                $statement .= $sep;
                $statement .= $this->protectIdentifier($column, self::PI_COLUMN);
                $statement .= '=:';
                $statement .= $column;
                $sep = ',';
            }
        } else {
            throw new LogicException('No columns set for UPDATE');
        }

        if (!empty($this->condition)) {
            $statement .= ' WHERE ';
            $statement .= $this->resolveDeferredModels($this->condition);
        }
        if (!empty($this->orderBy)) {
            $sep = '';
            $statement .= ' ORDER BY ';
            foreach ($this->orderBy as $column) {
                $statement .= $sep;
                $statement .= $this->protectIdentifier($column);
                $sep = ',';
            }
        }
        if (!empty($this->limit)) {
            $statement .= ' LIMIT ' . $this->limit;
        }

        // Handle RETURNING clause for PostgreSQL
        if (!empty($this->returning)) {
            $statement .= ' RETURNING ';
            $statement .= implode(', ', $this->protectColumns($db, $this->returning));
        }

        return $statement;
    }

    /**
     * Compile TRUNCATE statement
     * @return string
     * @throws LogicException
     */
    protected function compileTruncate(Database $db): string
    {
        if (empty($this->table)) {
            throw new LogicException('No table set for TRUNCATE query');
        }

        switch ($db->getDriver()) {
            case 'mysql':
                return 'TRUNCATE ' . $this->table;
            case 'pgsql':
                return 'TRUNCATE ' . $this->table . ' RESTART IDENTITY';
            default:
                throw new LogicException('TRUNCATE not supported for this database driver');
        }
    }

    /**
     * Compile DELETE statement
     * @return string
     * @throws LogicException
     */
    protected function compileDelete(Database $db): string
    {
        if (empty($this->table)) {
            throw new LogicException('No table set for DELETE query');
        }

        $statement = 'DELETE FROM ' . $this->table;

        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                if (!empty($join['type'])) {
                    $statement .= ' ';
                    $statement .= $join['type'];
                }
                $statement .= ' JOIN ';
                $statement .= $join['table'];
                $statement .= ' ON (';
                $statement .= $this->compileCondition($join['conditions']);
                $statement .= ')';
            }
        }

        if (!empty($this->condition)) {
            $statement .= ' WHERE ';
            $statement .= $this->resolveDeferredModels($this->condition);
        }
        if (!empty($this->orderBy)) {
            $sep = '';
            $statement .= ' ORDER BY ';
            foreach ($this->orderBy as $column) {
                $statement .= $sep;
                $statement .= $this->protectIdentifier($column);
                $sep = ',';
            }
        }

        if (!empty($this->limit)) {
            $statement .= ' LIMIT ' . $this->limit;
        }

        // Handle RETURNING clause for PostgreSQL
        if (!empty($this->returning)) {
            $statement .= ' RETURNING ';
            $statement .= implode(', ', $this->protectColumns($db, $this->returning));
        }

        return $statement;
    }

    /**
     * Compile EXISTS statement
     * @return string
     * @throws LogicException
     */
    protected function compileExists(Database $db): string
    {
        // Build a basic SELECT to wrap
        $innerQuery = $this->compileSelect($db);
        return 'SELECT EXISTS(' . $innerQuery . ') as __mrln_exists__';
    }

    /**
     * Compile COUNT statement
     * @return string
     * @throws LogicException
     */
    protected function compileCount(Database $db): string
    {
        if (empty($this->table)) {
            throw new LogicException('No table set for COUNT query');
        }

        $statement = 'SELECT COUNT(*) as __mrln_cnt__ FROM ';
        $statement .= $this->table;

        if (isset($this->joins)) {
            foreach ($this->joins as $join) {
                if (!empty($join['type'])) {
                    $statement .= ' ';
                    $statement .= $join['type'];
                }
                $statement .= ' JOIN ';
                $statement .= $join['table'];
                $statement .= ' ON (';
                $statement .= $this->compileCondition($join['conditions']);
                $statement .= ')';
            }
        }
        if (!empty($this->condition)) {
            $statement .= ' WHERE ';
            $statement .= $this->resolveDeferredModels($this->condition);
        }

        return $statement;
    }

    /* -------------------------------------------------------------
     *  INFRASTRUCTURE METHODS
     * ------------------------------------------------------------- */

    protected function prepareQueryForReturn(string $query)
    {
        // Get all bind parameters for this query
        $bindParams = $this->getBindings();

        // Replace bound parameters in query string for debugging purposes
        foreach ($bindParams as $key => $value) {
            $placeholder = ':' . $key;
            if (is_string($value)) {
                $replacement = $this->escapeValue($value);
            } elseif (is_scalar($value) || $value === null) {
                $replacement = $this->serializeScalar($value);
            } else {
                // For arrays or objects, we can't serialize to a scalar value, so just indicate the type
                $replacement = '[[' . gettype($value) . ']]';
            }
            $query = str_replace($placeholder, $replacement, $query);
        }

        return $query;
    }

    /**
     * Hook: resolve table name immediately via model resolution
     * @param string $model
     * @return string
     * @throws Exception
     */
    protected function resolveTableNameOrDefer(string $model): string
    {
        // In Query we can resolve immediately using model cache/instantiation.
        // But we must not break plain table/alias names when models are enabled.
        try {
            $canResolve = (isset(self::$modelMapping) && self::$modelMapping->get($model) !== null) || (self::$useModels && class_exists($model));
            if ($canResolve) {
                // Will also populate $this->tableCache.
                return $this->getFullTableName($model, null);
            }
        } catch (\Throwable $e) {
            // Fall back to escaping below.
        }
        // Plain table name or alias.
        return $this->quoteIdentifier($model);
    }

    /**
     * Get full table name with model resolution and schema handling
     * @param string $modelName
     * @param string|null $alias
     * @return string
     * @throws Exception
     */
    protected function getFullTableName(string $modelName, ?string $alias): string
    {
        if (isset(self::$modelMapping)) {
            // Get table from model mapping
            $cacheItem = self::$modelMapping->get($modelName);
            if (!isset($cacheItem)) {
                throw new Exception("Model '$modelName' not found in model mapping");
            }
            if (empty($cacheItem['source'])) {
                throw new Exception("Model '$modelName' does not have a source defined in model mapping");
            }
            $table = $this->quoteIdentifier($cacheItem['source']);
            $schema = $cacheItem['schema'] ?? null;
        } elseif (self::$useModels) {
            // Get table from model instance
            $model = self::getModel($modelName);
            $table = $this->quoteIdentifier($model->source());
            $schema = $model->schema();
        } else {
            // Use model name as table name
            $items = explode('.', $modelName);
            $table = '';
            $sep = '';
            foreach ($items as $item) {
                $table .= $sep;
                $table .= $this->quoteIdentifier($item);
                $sep = '.';
            }
        }

        if (!empty($alias)) {
            $escapedAlias = $this->quoteIdentifier($alias);
            $this->tableCache[$alias] = $escapedAlias;
        } else {
            $this->tableCache[$modelName] = $table;
        }

        if (!empty($schema)) {
            $table = $this->quoteIdentifier($schema) . '.' . $table;
        }

        if (isset($escapedAlias)) {
            $table .= ' AS ' . $escapedAlias;
        }
        return $table;
    }

    /**
     * Compile a condition string
     * @param string|Condition $condition
     * @return string
     * @throws Exception
     */
    protected function compileCondition(string|Condition $condition): string
    {
        if ($condition instanceof Condition) {
            // Inject model resolver into condition for this query context
            $condition->injectModelResolver(function ($model) {
                return $this->getTableName($model);
            });
            // Merge auto-bind parameters from sub-condition
            $this->subQueryBindings = $condition->getBindings() + $this->subQueryBindings;
            return $condition->toSql();
        }

        // Raw string - protect identifiers
        return $this->protectConditionString($condition);
    }

    /**
     * Protect columns in query
     * @return array
     * @throws Exception
     */
    protected function protectColumns(Database $db, array $columns = null): array
    {
        $columnsToProtect = $columns;

        $protected = [];

        foreach ($columnsToProtect as $index => $column) {

            if (is_string($column)) {
                $protected[$index] = $this->protectIdentifier(
                    $column,
                    self::PI_COLUMN
                );
                continue;
            }

            if ($column instanceof Condition) {

                // Inject model resolver for this query context
                $column->injectModelResolver(
                    fn($model) => $this->getTableName($model)
                );

                $protected[$index] = '(' . $column->toSql() . ')';
                continue;
            }

            if ($column instanceof Sql) {
                $protected[$index] = $column->toSql(
                    $db->getDriver(),
                    fn($v) => $this->serializeScalar($v),
                    fn($identifier) => $this->protectIdentifier($identifier, self::PI_COLUMN)
                );
                continue;
            }

            throw new LogicException(
                "Unsupported column type: " . get_debug_type($column)
            );
        }

        return $protected;
    }

    /**
     * Get bind parameters
     */
    public function getBindings(): array
    {
        return $this->manualBindings + $this->subQueryBindings;
    }

    /**
     * Create a paginator for the current query
     * @param int $page Page number (1-based)
     * @param int $pageSize Number of items per page
     * @param bool $reverse Whether to reverse the order of results (for efficient deep pagination)
     * @return Paginator
     */
    public function paginate(
        int $page = 1,
        int $pageSize = 30,
        bool $reverse = false
    ): Paginator {
        return new Paginator($this, $page, $pageSize, $reverse);
    }

    /**
     * Executes a statement using the parameters built with the criteria
     * @return ResultSet|bool
     * @throws Exception
     */
    protected function executeQuery(Database $db, string $query): bool|ResultSet
    {
        $bindParams = $this->getBindings();

        $result = $db->query($query, $bindParams);

        $this->rowCount = $db->rowCount();

        // Result is either a PDOStatement for read queries or a boolean for write queries without RETURNING clauses
        if (!$result instanceof PDOStatement) {
            return $result;
        }

        return new ResultSet(
            $db,
            $result,
            $query,
            $bindParams,
            $this->model
        );
    }

    /**
     * Return the number of affected rows for write operations or the number of rows in the result set for read operations
     * @return int
     */
    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
