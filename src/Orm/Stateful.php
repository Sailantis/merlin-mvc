<?php

namespace Azera\Orm;

use ReflectionClass;

/**
 * Storage-agnostic base for persisted objects.
 *
 * Holds ONLY in-memory state tracking: a snapshot of the object taken via
 * {@see saveState()}, dirty detection against that snapshot, and reverting
 * to it ({@see loadState()}). It deliberately knows nothing about
 * connections, query builders, sources, tenants, or the ORM heap — those
 * concerns live in subclasses (Active-Record {@see \Azera\Core\Model}) or
 * in the ORM layer (Heap/UnitOfWork). If you ever notice this base reaching
 * for a connection, the seam has rotted.
 *
 * Field conventions: any property whose name starts with "__" is considered
 * internal and excluded from snapshots and change detection.
 */
#[\AllowDynamicProperties]
abstract class Stateful
{
    /* -------------------------------------------------------------
     *  STATE HANDLING
     * ------------------------------------------------------------- */

    protected $__state;

    /**
     * Save the current state of the object for change tracking. This method clones the current instance and stores it in the __state property. It should be called after loading or saving the object to establish a baseline for detecting changes.
     * @return $this
     */
    public function saveState(): static
    {
        $this->__state = clone $this;
        return $this;
    }

    /**
     * Load the saved state of the object back into the current instance. This method copies all properties from the __state clone back to the current instance, except for any properties that start with '__' which are considered internal and excluded from state tracking. It should be called before saving if you want to revert any unsaved changes back to the last saved state.
     * @return $this
     */
    public function loadState(): static
    {
        $state = $this->__state ?? null;
        if ($state) {
            $excluded = self::__getExcludedProperties();
            foreach ($state as $field => $value) {
                if (!isset($excluded[$field])) {
                    $this->$field = $value;
                }
            }
        }
        return $this;
    }

    /**
     * Get the saved state object for this object. This returns the clone of the object that was saved by saveState(), or null if no state has been saved. You can use this to inspect the original values before changes were made.
     * @return static|null The saved state object or null if no state saved
     */
    public function getState(): ?static
    {
        return $this->__state;
    }

    protected function __updateState(array $values): void
    {
        if ($this->__state) {
            foreach ($values as $k => $v) {
                $this->__state->$k = $v;
            }
        }
    }

    protected static array $__excludedPropertiesCache = [];

    protected static function __getExcludedProperties(): array
    {
        $class = static::class;

        if (!isset(self::$__excludedPropertiesCache[$class])) {
            $excluded = [];
            $reflect  = new ReflectionClass($class);

            foreach ($reflect->getProperties() as $prop) {
                if (str_starts_with($prop->name, '__')) {
                    $excluded[$prop->name] = true;
                }
            }

            self::$__excludedPropertiesCache[$class] = $excluded;
        }

        return self::$__excludedPropertiesCache[$class];
    }

    /**
     * Return the fields that changed since the last saveState() call.
     * Without a snapshot, all non-internal fields count as changed.
     * Internal "__"-prefixed properties are always excluded.
     */
    protected function __getChangedValues(): array
    {
        $excluded = self::__getExcludedProperties();
        $current  = array_diff_key(get_object_vars($this), $excluded);

        if ($this->__state) {
            $original = array_diff_key(get_object_vars($this->__state), $excluded);
            return array_diff_assoc($current, $original);
        }

        return $current;
    }

    /**
     * Check if any fields have changed since the last saveState() call. This compares the current field values to the saved state and returns true if there are any differences, or false if all values are the same. It ignores any properties that start with '__' as they are considered internal.
     * @return bool True if any fields have changed, false otherwise
     */
    public function hasChanged(): bool
    {
        return !empty($this->__getChangedValues());
    }
}