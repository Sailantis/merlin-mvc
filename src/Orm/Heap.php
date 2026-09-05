<?php

namespace Azera\Orm;

use Azera\Lifecycle\RequestScoped;

/**
 * Identity map: ONE node per persisted entity, keyed by class + PK values.
 *
 * Purpose is CORRECTNESS, not caching: the same DB row loaded twice in one
 * request yields the same object, and the EntityManager diffs against the
 * node snapshot instead of scanning every constructed instance. The heap is
 * request-scoped — {@see resetState()} wipes everything between requests in
 * persistent workers (non-negotiable; a leaking heap would return stale
 * entities for a new request / another tenant).
 *
 * Performance shape: two flat array lookups per access (identity index and
 * oid index), no objects allocated for lookups.
 */
final class Heap implements RequestScoped
{
    /** @var array<string, Node> identityKey => node */
    private array $nodes = [];

    /** @var array<int, Node> spl_object_id(entity) => node */
    private array $oids = [];

    /**
     * Retains the entity itself so its spl_object_id cannot be recycled by
     * the GC while the node is attached. Without this, an entity that only
     * lives inside the heap would be collected, its oid reused by the next
     * object, and the oid index silently corrupted.
     *
     * @var array<int, object> spl_object_id => entity
     */
    private array $entities = [];

    /**
     * Reverse index for O(1) entityFor(): spl_object_id(node) =>
     * spl_object_id(entity). entityFor() is on the flush hot path (every
     * scheduled write resolves its entity through it); a linear scan over
     * $entities made flush cost grow with total tracked entities.
     *
     * @var array<int, int> spl_object_id(node) => spl_object_id(entity)
     */
    private array $nodeOids = [];

    /**
     * Build the composite identity key for an entity.
     *
     * Assoc id arrays are canonicalized (ksort) so ['a'=>1,'b'=>2] and
     * ['b'=>2,'a'=>1] produce the SAME key. List-form id arrays keep their
     * value order (position defines the field).
     *
     * Fast path: single-PK scalar ids (the overwhelmingly common shape —
     * e.g. ['id' => 42]) skip ksort/array_is_list and build the key with
     * one interpolation. Composite keys keep the full canonicalization.
     *
     * @param class-string         $class
     * @param array<string, mixed> $id    PK field => value (all values non-null)
     */
    public static function key(string $class, array $id): string
    {
        // Fast path: exactly one PK, field name known at call site.
        if (\count($id) === 1) {
            $field = \key($id);
            return $class . '|' . $field . '=' . \current($id);
        }

        $isList = $id === [] || array_is_list($id);
        if (!$isList) {
            ksort($id);
        }

        $parts = [$class];
        foreach ($id as $field => $value) {
            $parts[] = $field . '=' . $value;
        }

        return implode('|', $parts);
    }

    /**
     * Register (or replace) the node for an entity.
     */
    public function attach(object $entity, Node $node): void
    {
        $oid = \spl_object_id($entity);

        // If this entity object was previously attached under another key,
        // drop the stale oid mapping so it cannot leak across identities.
        // Single isset() instead of null-coalesce: no node allocation on hit.
        if (isset($this->oids[$oid]) && $this->oids[$oid] !== $node) {
            $stale = $this->oids[$oid];
            unset($this->nodes[self::key($stale->class, $stale->id)], $this->nodeOids[spl_object_id($stale)]);
        }

        $key = self::key($node->class, $node->id);

        $this->nodes[$key]                    = $node;
        $this->oids[$oid]                     = $node;
        $this->entities[$oid]                 = $entity;
        $this->nodeOids[spl_object_id($node)] = $oid;
    }

    /**
     * Find the node for an entity OBJECT (regardless of its identity).
     */
    public function find(object $entity): ?Node
    {
        return $this->oids[\spl_object_id($entity)] ?? null;
    }

    /**
     * Find the node for a class + PK values — the identity-map hit path.
     *
     * @param class-string         $class
     * @param array<string, mixed> $id
     */
    public function findById(string $class, array $id): ?Node
    {
        return $this->nodes[self::key($class, $id)] ?? null;
    }

    /**
     * Drop an entity from identity tracking (after delete or detach).
     */
    public function detach(object $entity): void
    {
        $oid  = \spl_object_id($entity);
        $node = $this->oids[$oid] ?? null;

        if ($node !== null) {
            unset($this->nodes[self::key($node->class, $node->id)], $this->oids[$oid], $this->entities[$oid], $this->nodeOids[spl_object_id($node)]);
        }
    }

    /**
     * All nodes currently scheduled for a flush, in insertion order.
     *
     * @return list<Node>
     */
    public function scheduled(): array
    {
        $out = [];
        foreach ($this->nodes as $node) {
            if ($node->isScheduled()) {
                $out[] = $node;
            }
        }

        return $out;
    }

    /**
     * Resolve the entity object a node was attached with (flush-time
     * backfill needs the actual instance, not just its bookkeeping node).
     * O(1) via the reverse node => oid index.
     */
    public function entityFor(Node $node): ?object
    {
        $oid = $this->nodeOids[spl_object_id($node)] ?? null;

        return $oid === null ? null : $this->entities[$oid];
    }

    /**
     * All nodes (any state), in insertion order.
     *
     * @return list<Node>
     */
    public function all(): array
    {
        return \array_values($this->nodes);
    }

    public function count(): int
    {
        return \count($this->nodes);
    }

    /**
     * Request-scoped hook: wipe the entire identity map. Called between
     * requests in persistent workers. This is a correctness requirement —
     * never turn the heap into a cross-request cache.
     */
    public function resetState(): void
    {
        $this->nodes    = [];
        $this->oids     = [];
        $this->entities = [];
        $this->nodeOids = [];
    }
}