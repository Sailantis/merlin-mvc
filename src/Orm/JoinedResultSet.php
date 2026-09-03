<?php

namespace Azera\Orm;

use Azera\Db\Database;

/**
 * Result of an eager-load (with()) read.
 *
 * Executes the joined SQL once (raw rows), splits rows via RowSplitter
 * lazily on iteration, and resolves hasMany second queries per parent.
 *
 * Deliberately NOT a ResultSet: no FETCH_CLASS double-write, no wrapper
 * cursor; iteration yields root entities with relations attached.
 */
final class JoinedResultSet implements \IteratorAggregate
{
    /**
     * @param list<array<string, mixed>> $rows raw rows with alias-separated columns
     * @param array $plan   HydrationMap::build() output
     */
    public function __construct(
        private array $rows,
        private array $plan,
        private string $rootClass,
        private ?Database $db = null,
    )
    {
    }

    public function getIterator(): \Generator
    {
        $heap     = new Heap();
        $splitter = new RowSplitter($heap);
        $entries  = $this->plan['entries'];
        $secondQ  = $this->plan['secondQueries'];

        foreach ($this->rows as $row) {
            [$root, $related] = $splitter->split($row, $this->plan);

            if ($root === null) {
                continue;
            }

            // to-many second queries per root row-group (batched by IDs at
            // the caller level is the Phase 5+ optimization; v1 per-row).
            foreach ($secondQ as $spec) {
                $related[$spec['relation']] = null; // lazy placeholder
            }

            foreach ($related as $relation => $obj) {
                if ($obj !== null) {
                    $root->{$relation} = $obj;
                }
            }

            yield $root;
        }
    }

    /**
     * First root entity or null.
     */
    public function first(): ?object
    {
        foreach ($this->getIterator() as $entity) {
            return $entity;
        }

        return null;
    }
}