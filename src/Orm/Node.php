<?php

namespace Azera\Orm;

/**
 * A single entity's bookkeeping entry in the {@see Heap}.
 *
 * Mirrors Cycle's Node concept, trimmed to what the EntityManager's write
 * pipeline needs:
 * the entity reference, its identity (PK values), a data snapshot for
 * dirty diffing, and the persistence lifecycle state.
 *
 * The data array holds SCALAR row values only (the raw store representation) —
 * not PHP objects. Objects would break both the diff and the L2 cache story.
 */
final class Node
{
    /** Created in-memory, never persisted. No snapshot yet. */
    public const NEW = 1;

    /** Loaded from storage (or successfully flushed). Snapshot = last known store values. */
    public const MANAGED = 2;

    /** Queued for INSERT at the next flush. */
    public const SCHEDULED_INSERT = 3;

    /** Queued for UPDATE at the next flush. */
    public const SCHEDULED_UPDATE = 4;

    /** Queued for DELETE at the next flush. */
    public const SCHEDULED_DELETE = 5;

    /** Removed from storage; entity detached from identity tracking. */
    public const DELETED = 6;

    public function __construct(
        public readonly string $class,
        public readonly array $id,
        public array $data,
        public int $state = self::NEW,
        public array $changedFields = [],
    ) {}

    public function isScheduled(): bool
    {
        return $this->state === self::SCHEDULED_INSERT
            || $this->state === self::SCHEDULED_UPDATE
            || $this->state === self::SCHEDULED_DELETE;
    }
}