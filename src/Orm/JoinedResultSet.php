<?php

namespace Azera\Orm;

use Azera\AppContext;
use Azera\Db\Database;

/**
 * Result of an eager-load (with()) read.
 *
 * Executes the joined SQL once (raw rows), splits rows via RowSplitter,
 * and materializes to-many relations with ONE batched second query per
 * relation: `WHERE fk IN (rootIds)`, grouped and assigned. This is the
 * Cycle INLOAD / Eloquent-proven strategy — joining to-many would
 * duplicate parent rows, so it never does.
 *
 * All entities hydrate into the REQUEST-SCOPED heap (AppContext::heap()),
 * so a parent joined across many rows — or read again later in the same
 * request — is the same object instance, and to-many children attach to
 * the same identity map the UnitOfWork uses.
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
        $heap     = AppContext::instance()->heap();
        $splitter = new RowSplitter($heap);

        // 1) Split every flat row into root + joined to-one entities.
        //    Heap dedup makes repeated roots (multi-row to-many joins or
        //    repeated FKs) resolve to the SAME object.
        $roots = [];
        foreach ($this->rows as $row) {
            [$root, $related] = $splitter->split($row, $this->plan);

            if ($root === null) {
                continue;
            }

            foreach ($related as $relation => $obj) {
                if ($obj !== null) {
                    $root->{$relation} = $obj;
                }
            }

            $roots[] = $root;
        }

        // Same root object may have arrived via several rows — collapse.
        $unique = [];
        foreach ($roots as $root) {
            $unique[\spl_object_id($root)] = $root;
        }
        $roots = \array_values($unique);

        // 2) To-many relations: one batched second query per relation,
        //    grouped by FK and assigned. LEFT-JOIN row multiplication is
        //    structurally impossible in this strategy.
        if ($this->plan['secondQueries'] !== [] && $roots !== []) {
            $this->loadToMany($roots, $this->plan['secondQueries'], $heap);
        }

        yield from $roots;
    }

    /**
     * One batched `WHERE fk IN (rootIds)` query per to-many relation.
     *
     * @param list<object> $roots
     * @param list<array{relation: string, class: class-string, foreignKey: string, ownerKey: string}> $specs
     */
    private function loadToMany(array $roots, array $specs, Heap $heap): void
    {
        $db = $this->db
            ?? throw new \LogicException('No connection available for to-many eager load');

        foreach ($specs as $spec) {
            $relation   = $spec['relation'];
            $target     = $spec['class'];
            $foreignKey = $spec['foreignKey']; // column on the target table
            $ownerKey   = $spec['ownerKey'];   // field on the root entity

            $ids = [];
            foreach ($roots as $root) {
                $value = $root->{$ownerKey} ?? null;
                if ($value !== null) {
                    $ids[(string) $value] = $value;
                }
            }

            if ($ids === []) {
                foreach ($roots as $root) {
                    $root->{$relation} = [];
                }
                continue;
            }

            $values = \array_values($ids);
            $meta   = Metadata::for($target);
            $table  = $db->quoteIdentifier($meta['source']);
            $fkCol  = $db->quoteIdentifier($foreignKey);

            $placeholders = implode(', ', \array_fill(0, \count($values), '?'));
            $rows         = $db->selectAll(
                "SELECT * FROM {$table} WHERE {$fkCol} IN ({$placeholders})",
                $values,
                \PDO::FETCH_ASSOC
            );

            // Hydrate into the shared heap (identity) and group by FK.
            $hydrator = FastHydrator::for($target);
            $grouped  = [];
            foreach ($rows as $row) {
                [$entity] = $hydrator->hydrate($heap, $row);
                if ($entity === null) {
                    continue;
                }
                $key = $row[$foreignKey] ?? null;
                if ($key !== null) {
                    $grouped[(string) $key][] = $entity;
                }
            }

            foreach ($roots as $root) {
                $key = $root->{$ownerKey} ?? null;
                $root->{$relation} = $key !== null ? ($grouped[(string) $key] ?? []) : [];
            }
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