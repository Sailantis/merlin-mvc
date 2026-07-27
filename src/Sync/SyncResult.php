<?php
namespace Azera\Sync;

/**
 * Holds the result of synchronising a single model file against the database schema.
 */
class SyncResult
{
    /**
     * @param string          $filePath     Absolute path to the model file
     * @param string          $className    Fully-qualified class name
     * @param string          $tableName    Database table that was introspected
     * @param DiffOperation[] $operations   All diff operations calculated
     * @param bool            $applied      Whether the operations were written to disk
     * @param string|null     $error        Error message, or null on success
     */
    public function __construct(
        public string $filePath,
        public string $className,
        public string $tableName,
        public array $operations,
        public bool $applied,
        public ?string $error = null
    ) {
    }

    public function hasChanges(): bool
    {
        return !empty($this->operations);
    }

    public function isSuccess(): bool
    {
        return $this->error === null;
    }

    /** @return AddProperty[] */
    public function addedProperties(): array
    {
        return array_values(array_filter($this->operations, fn($op) => $op instanceof AddProperty));
    }

    /** @return RemoveProperty[] */
    public function removedProperties(): array
    {
        return array_values(array_filter($this->operations, fn($op) => $op instanceof RemoveProperty));
    }

    /** @return UpdatePropertyType[] */
    public function typeChanges(): array
    {
        return array_values(array_filter($this->operations, fn($op) => $op instanceof UpdatePropertyType));
    }

    /** @return AddAccessor[] */
    public function addedAccessors(): array
    {
        return array_values(array_filter($this->operations, fn($op) => $op instanceof AddAccessor));
    }

    /**
     * Human-readable summary line.
     */
    public function summary(): string
    {
        if ($this->error) {
            return "[ERROR] {$this->className}: {$this->error}";
        }

        if (!$this->hasChanges()) {
            return "[OK]    {$this->className}: no changes";
        }

        $parts = [];
        $added = count($this->addedProperties());
        $removed = count($this->removedProperties());
        $typed = count($this->typeChanges());
        $accessors = count($this->addedAccessors());

        if ($added)
            $parts[] = "+{$added} added";
        if ($accessors)
            $parts[] = "+{$accessors} accessor(s)";
        if ($removed)
            $parts[] = "-{$removed} deprecated";
        if ($typed)
            $parts[] = "~{$typed} type changed";

        $status = $this->applied ? 'APPLIED' : 'DRY-RUN';
        return "[{$status}] {$this->className} ({$this->tableName}): " . implode(', ', $parts);
    }
}
