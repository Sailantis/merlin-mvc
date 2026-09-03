<?php

namespace Azera\Core;

use Azera\AppContext;
use Azera\Db\Query;
use Azera\Orm\Stateful;
use Azera\Db\Database;
use Azera\Db\ResultSet;
use Azera\Db\Resolver\ModelResolver;

/**
 * Base class for models in Azera.
 * @template T of Model
 * @method static Query<T> query()
 * @method static ResultSet<T> findAll(array $conditions = [])
 */
#[\AllowDynamicProperties]
abstract class Model extends Stateful
{
	/* -------------------------------------------------------------
	 *  MODEL CONFIG
	 * ------------------------------------------------------------- */

	/**
	 * Return the table or view name for this model. By default, it converts the
	 * short class name (without namespace) from CamelCase to snake_case and applies pluralization if enabled (e.g. User → users, AdminUser → admin_users, Person → people).
	 * Override this method if you want to specify a custom source.
	 */
	public function source(): string
	{
		if (isset($this->__sourceCache)) {
			return $this->__sourceCache;
		}
		$class = static::class;
		// Strip namespace to get short class name
		$pos = strrpos($class, '\\');
		if ($pos !== false) {
			$class = substr($class, $pos + 1);
		}
		// Convert to snake_case and apply pluralization if enabled
		$this->__sourceCache = ModelMapping::convertModelToSource($class);
		return $this->__sourceCache;
	}

	protected $__sourceCache;

	/**
	 * Return the database schema for this model, if applicable. By default, it returns null.
	 * Override this method if you want to specify a schema (e.g. for PostgreSQL).
	 */
	public function schema(): ?string
	{
		return null;
	}

	/**
	 * Return the name of the primary key field(s) for this model. By default, it returns ['id'].
	 * Override this method if your model has a different primary key or composite keys.
	 * @return array List of primary key field names
	 */
	public function idFields(): array
	{
		return ['id'];
	}

	/* -------------------------------------------------------------
	 *  STATIC QUERY BUILDER
	 * ------------------------------------------------------------- */

	/**
	 * Start a new query builder for this model. By default, it creates a Query with the model's source as the table.
	 * You can also use selectBuilder(), insertBuilder(), updateBuilder(), and deleteBuilder() for more specific builders.
	 * @param string|null $alias Optional alias for the model in the query
	 * @return Query<static>
	 */
	public static function query(?string $alias = null): Query
	{
		return (new Query)
			->using(AppContext::instance()->get(ModelResolver::class))
			->table(static::class, $alias);
	}

	/**
	 * Start a query with eager-loaded relations.
	 * BelongsTo/HasOne become LEFT JOINs (one SQL, alias-separated rows);
	 * HasMany stays a second query by parent IDs. Relation names must be
	 * declared via Orm attributes on the model.
	 *
	 * @param string ...$relations Relation names from Orm metadata
	 * @return Query<static>
	 */
	public static function with(string ...$relations): Query
	{
		$query = static::query();

		foreach ($relations as $name) {
			$query->with($name);
		}

		return $query;
	}

	/* -------------------------------------------------------------
	 *  STATIC CREATE METHODS
	 * ------------------------------------------------------------- */

	/**
	 * Create a new model instance with the given values and save it to the database. Returns the created instance.
	 * @param array $values Associative array of field values to set on the new model
	 * @return static The created model instance
	 */
	public static function create(array $values): static
	{
		$instance = new static();

		foreach ($values as $key => $value) {
			$instance->$key = $value;
		}

		$instance->save(); // INSERT or UPDATE
		return $instance;
	}

	/**
	 * Force create a new model instance with the given values, bypassing any checks for required fields or IDs. This is useful for seeding or when you want to manually set all fields including IDs. Returns the created instance.
	 * @param array $values Associative array of field values to set on the new model
	 * @return static The created model instance
	 */
	public static function forceCreate(array $values): static
	{
		$instance = new static();

		foreach ($values as $key => $value) {
			$instance->$key = $value;
		}

		$instance->__performWrite($values, false);
		$instance->saveState();

		return $instance;
	}

	/**
	 * Create or update a model using database-level UPSERT semantics
	 * (INSERT ... ON CONFLICT DO UPDATE).  Unlike {@see updateOrCreate()},
	 * this performs a single query with no prior SELECT — the database
	 * handles the conflict resolution atomically.
	 *
	 * All ID fields must be present in $values so the conflict target is
	 * well-defined.  On conflict, all non-ID fields from $values are
	 * updated.
	 *
	 * @param array $values Associative array of field values (must include all ID fields)
	 * @return static The model instance with the given values
	 */
	public static function upsert(array $values): static
	{
		$instance = new static();

		foreach ($values as $key => $value) {
			$instance->$key = $value;
		}

		$instance->__performWrite($values, true);

		return $instance;
	}

	/**
	 * Find the first model matching the given conditions or create a new one with the combined conditions and values if none found. This is useful for ensuring a record exists without creating duplicates. Returns the found or created instance.
	 * @param array $conditions Associative array of field conditions to find the model
	 * @param array $values Additional values to set on the model if it needs to be created (merged with conditions)
	 * @return static The found or created model instance
	 */
	public static function firstOrCreate(array $conditions, array $values = []): static
	{
		$model = static::findOne($conditions);

		if ($model) {
			return $model;
		}

		return static::create(array_merge($conditions, $values));
	}

	/**
	 * Find the first model matching the given conditions or update it with the provided values if found, otherwise create a new one with the combined conditions and values. This is useful for ensuring a record exists and is up to date without creating duplicates. Returns the found, updated, or created instance.
	 * @param array $conditions Associative array of field conditions to find the model
	 * @param array $values Values to set on the model if found (updated) or merged with conditions if created
	 * @return static The found, updated, or created model instance
	 */
	public static function updateOrCreate(array $conditions, array $values = []): static
	{
		$model = static::findOne($conditions);

		if ($model) {
			foreach ($values as $key => $value) {
				$model->$key = $value;
			}
			$model->save();
			return $model;
		}

		return static::create(array_merge($conditions, $values));
	}

	/* -------------------------------------------------------------
	 *  LOAD METHODS
	 * ------------------------------------------------------------- */

	/**
	 * Finds a model by its ID(s)
	 * @param mixed $id Single ID value or array of ID values (for composite keys)
	 */
	public static function find(mixed $id): ?static
	{
		$instance = new static();
		$builder  = static::query();

		$idFields = $instance->idFields();

		if (\is_array($id)) {
			if (\count($id) !== \count($idFields)) {
				throw new Exception("ID array count mismatch");
			}
			if (isset($idFields[0])) {
				// Numeric array: assume order matches idFields
				foreach ($idFields as $i => $field) {
					if (!isset($id[$i])) {
						throw new Exception("Missing ID value for field '$field'");
					}
					$builder->where($field, $id[$i]);
				}
			} else {
				// Associative array: keys must match idFields
				foreach ($idFields as $field) {
					if (!isset($id[$field])) {
						throw new Exception("Missing ID value for field '$field'");
					}
					$builder->where($field, $id[$field]);
				}
			}
		} else {
			$builder->where($idFields[0], $id);
		}

		return $builder->first();
	}

	/**
	 * Finds a model by its ID(s) or throws an exception if not found
	 * @param mixed $id Single ID value or array of ID values (for composite keys)
	 * @throws Exception if the model is not found
	 */
	public static function findOrFail(mixed $id): static
	{
		$model = static::find($id);
		if (!$model) {
			throw new Exception(static::class . " not found");
		}
		return $model;
	}

	/**
	 * Finds the first model matching the given conditions or returns null if none found.
	 * @param array $conditions Associative array of field conditions to find the model
	 * @return static|null The found model instance or null if not found
	 */
	public static function findOne(array $conditions): ?static
	{
		$builder = static::query();
		foreach ($conditions as $field => $value) {
			$builder->where($field, $value);
		}
		return $builder->first();
	}

	/**
	 * Find all models matching the given conditions. If no conditions are provided, it returns all models. Returns a ResultSet of model instances.
	 * @param array $conditions Associative array of field conditions to find the models
	 * @return ResultSet<static> The found model instances as a ResultSet
	 */
	public static function findAll(array $conditions = []): ResultSet
	{
		$builder = static::query();
		foreach ($conditions as $field => $value) {
			$builder->where($field, $value);
		}
		return $builder->select();
	}

	/**
	 * Check if any model exists matching the given conditions. Returns true if at least one record matches, false otherwise.
	 * @param array $conditions Associative array of field conditions to check for existence
	 * @return bool True if a matching model exists, false otherwise
	 */
	public static function exists(array $conditions): bool
	{
		$builder = static::query();
		foreach ($conditions as $field => $value) {
			$builder->where($field, $value);
		}
		return $builder->exists();
	}

	/**
	 * Count the number of models matching the given conditions. Returns the count as an integer. count() is an alias for count() to avoid collision with database fields named "count".
	 * @param array $conditions Associative array of field conditions to count
	 * @return int The count of matching models
	 */
	public static function count(array $conditions = []): int
	{
		$builder = static::query();
		foreach ($conditions as $field => $value) {
			$builder->where($field, $value);
		}
		return $builder->count();
	}

	/* -------------------------------------------------------------
	 *  SAVE / UPDATE / INSERT / UPSERT
	 * ------------------------------------------------------------- */

	/**
	 * Save the model to the database. If the model has all ID fields set, it performs an UPDATE, otherwise it performs an INSERT. Returns true if the save was successful, false if there were no changes to save.
	 *
	 * @return bool True if the model was saved (inserted or updated), false if there were no changes to save
	 */
	public function save(): bool
	{
		$changed = $this->__getChangedValues();
		if (empty($changed)) {
			return false;
		}

		$idFields  = $this->idFields();
		$hasAllIds = true;

		foreach ($idFields as $field) {
			if (!isset($this->$field)) {
				$hasAllIds = false;
				break;
			}
		}

		if ($hasAllIds) {
			return $this->__performUpdate($changed);
		}

		return $this->__performWrite($changed, false);
	}

	/**
	 * Insert the model as a new record in the database. This method performs an INSERT regardless of whether ID fields are set. Returns true if the insert was successful.
	 *
	 * @param bool $upsert When true, performs an INSERT ... ON CONFLICT DO UPDATE instead of a plain INSERT.  Requires all ID fields to be set (they serve as the conflict target).
	 * @return bool True if the model was inserted successfully
	 */
	public function insert(bool $upsert = false): bool
	{
		$excluded = self::__getExcludedProperties();
		$values   = array_diff_key(get_object_vars($this), $excluded);

		$this->__performWrite($values, $upsert);
		$this->saveState();

		return true;
	}

	/**
	 * Update the existing record in the database with any changed fields. This method requires that all ID fields are set and will throw an exception if any are missing. Returns true if the update was successful, false if there were no changes to update.
	 * @return bool True if the model was updated successfully, false if there were no changes to update
	 */
	public function update(): bool
	{
		$changed = $this->__getChangedValues();
		if (empty($changed)) {
			return false;
		}

		return $this->__performUpdate($changed);
	}

	protected function __performUpdate(array $values): bool
	{
		foreach ($this->idFields() as $field) {
			unset($values[$field]);
		}

		if (empty($values)) {
			return false;
		}

		$builder = static::query();
		foreach ($this->idFields() as $field) {
			if (!isset($this->$field)) {
				throw new Exception("ID field '$field' not set");
			}
			$builder->where($field, $this->$field);
		}
		$builder->update($values);
		$this->__updateState($values);

		return true;
	}

	protected function __performWrite(array $values, bool $upsert): bool
	{
		$excluded = self::__getExcludedProperties();

		$idFields   = $this->idFields();
		$missingMap = [];
		foreach ($idFields as $field) {
			$missingMap[$field] = true;
		}

		foreach ($values as $field => $_) {
			if (isset($excluded[$field])) {
				unset($values[$field]);
				continue;
			}
			if ($values[$field] === null) {
				unset($values[$field]);
				continue;
			}
			if (isset($missingMap[$field])) {
				unset($missingMap[$field]);
			}
		}

		$missingCount = \count($missingMap);

		$builder = static::query();
		if ($upsert) {
			$builder->updateValues($values);
			$builder->conflict($idFields);
		}

		$db = $this->writeConnection();

		if ($db->supportsReturning()) {
			$result = $builder->returning(['*'])->insert($values);
			if ($result instanceof ResultSet && ($row = $result->fetchAssoc())) {
				foreach ($row as $field => $value) {
					if (isset($missingMap[$field]) || !isset($this->$field)) {
						$this->$field = $value;
					}
				}
				// Close the RETURNING cursor immediately. An open cursor on a
				// write statement keeps the connection's write lock held
				// (SQLite WAL), which would block writes from other
				// connections.
				$result->closeCursor();
			}
		} else {
			$result = $builder->insert($values);
			if ($missingCount === 1 && is_numeric($result)) {
				reset($missingMap);
				$field = key($missingMap);
				$this->$field = $result;
			}
		}

		$this->saveState();
		return true;
	}

	/* -------------------------------------------------------------
	 *  DELETE
	 * ------------------------------------------------------------- */

	/**
	 * Delete the model from the database. This method requires that all ID fields are set and will throw an exception if any are missing. Returns true if the delete was successful.
	 * @return bool True if the model was deleted successfully
	 */
	public function delete(): bool
	{
		$builder = static::query();
		foreach ($this->idFields() as $field) {
			if (!isset($this->$field)) {
				throw new Exception("ID field '$field' not set");
			}
			$builder->where($field, $this->$field);
		}
		$builder->delete();
		return true;
	}

	/* -------------------------------------------------------------
	 *  CONNECTIONS
	 * ------------------------------------------------------------- */

	/** @var array<string, string> */
	protected static array $__defaultReadRoles = [];

	/** @var array<string, string> */
	protected static array $__defaultWriteRoles = [];

	/**
	 * Set both the read and write database role for this model class.
	 *
	 * @param string $role Named role registered with {@see \Azera\Db\DatabaseManager}.
	 */
	public static function setDefaultRole(string $role): void
	{
		self::$__defaultReadRoles[static::class] = $role;
		self::$__defaultWriteRoles[static::class] = $role;
	}

	/**
	 * Set the database role used for SELECT queries on this model class.
	 *
	 * @param string $role Named read role registered with {@see \Azera\Db\DatabaseManager}.
	 */
	public static function setDefaultReadRole(string $role): void
	{
		self::$__defaultReadRoles[static::class] = $role;
	}

	/**
	 * Set the database role used for INSERT/UPDATE/DELETE queries on this model class.
	 *
	 * @param string $role Named write role registered with {@see \Azera\Db\DatabaseManager}.
	 */
	public static function setDefaultWriteRole(string $role): void
	{
		self::$__defaultWriteRoles[static::class] = $role;
	}

	protected function __connectionRole(string $type): string
	{
		$map = $type === 'read'
			? static::$__defaultReadRoles
			: static::$__defaultWriteRoles;

		// Check for specific model role
		if (isset($map[static::class])) {
			return $map[static::class];
		}

		// Check for base model role
		if (isset($map[self::class])) {
			return $map[self::class];
		}

		// Fallback to default role
		return $type;
	}

	/**
	 * Return the database connection role used for read (SELECT) queries.
	 *
	 * @return string The role name (e.g. 'read', 'replica').
	 */
	public function readRole(): string
	{
		return $this->__connectionRole('read');
	}

	/**
	 * Return the database connection role used for write (INSERT/UPDATE/DELETE) queries.
	 *
	 * @return string The role name (e.g. 'write', 'primary').
	 */
	public function writeRole(): string
	{
		return $this->__connectionRole('write');
	}

	/**
	 * Return the database connection used for read (SELECT) queries.
	 *
	 * Resolves the configured read role via {@see \Azera\Db\DatabaseManager::getOrDefault()}.
	 *
	 * @return \Azera\Db\Database
	 */
	public function readConnection(): Database
	{
		$role = $this->__connectionRole('read');
		return AppContext::instance()->dbManager()->getOrDefault($role);
	}

	/**
	 * Return the database connection used for write (INSERT/UPDATE/DELETE) queries.
	 *
	 * Resolves the configured write role via {@see \Azera\Db\DatabaseManager::getOrDefault()}.
	 *
	 * @return \Azera\Db\Database
	 */
	public function writeConnection(): Database
	{
		$role = $this->__connectionRole('write');
		return AppContext::instance()->dbManager()->getOrDefault($role);
	}

}