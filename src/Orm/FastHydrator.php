<?php

namespace Azera\Orm;

/**
 * Per-class compiled hydration plan.
 *
 * The generic HydrationMap + RowSplitter path re-walks the metadata arrays
 * for EVERY row and entity: `foreach ($meta['columns'] as ...)` twice plus
 * per-field array_key_exists checks. This class compiles the same plan into
 * flat scalar arrays (field names, column aliases, PK aliases) ONCE per
 * class, so hydrating a row is: one heap lookup, one instantiation, two
 * tightly-typed copy loops over plain lists — no metadata array walking.
 *
 * Same contract as HydrationMap::build() + RowSplitter::split() for the
 * single-root (no relations) case, which is the hot path for list reads.
 * Relations keep using the generic path (they are per-row by nature).
 *
 * L1-cached per class like Metadata; nothing else to configure.
 */
final class FastHydrator
{
    /** @var array<class-string, array> */
    private static array $plans = [];

    /** @var array<class-string, self> */
    private static array $instances = [];

    public string $class;

    /** @var list<string> field names, aligned with $columns */
    public array $fields = [];

    /** @var list<string> raw column names, aligned with $fields */
    public array $columns = [];

    /** @var array<string, string> field => column name (raw row keys) */
    public array $fieldToColumn = [];

    /** @var list<string> PK field names */
    public array $pkFields = [];

    /** @var list<string> raw column names for PK fields, aligned with $pkFields */
    public array $pkColumns = [];

    private function __construct(string $class, array $meta)
    {
        $this->class = $class;

        foreach ($meta['columns'] as $field => $col) {
            $this->fields[]              = $field;
            $this->columns[]             = $col['name'];
            $this->fieldToColumn[$field] = $col['name'];
            if ($col['pk']) {
                $this->pkFields[]  = $field;
                $this->pkColumns[] = $col['name'];
            }
        }
    }

    /** Per-class singleton plan (mirrors Metadata::for semantics). */
    public static function for(string $class): self
    {
        $class = ltrim($class, '\\');
        if (isset(self::$instances[$class])) {
            return self::$instances[$class];
        }
        return self::$instances[$class] = new self($class, Metadata::for($class));
    }

    /**
     * Compile a row -> [entity, id, snapshotData] triple.
     *
     * Identity-map probe FIRST: with the shared request-scoped heap, the
     * same row read twice in one request MUST yield the same object (a
     * per-query heap never faced this because it died with the query).
     * A hit returns the existing instance untouched — the heap snapshot
     * stays authoritative and in-request mutations are not clobbered.
     *
     * Cold path: build id + entity + snapshot in three tight list loops,
     * attach once.
     *
     * @param array<string, mixed> $row raw assoc row keyed by COLUMN name
     * @return array{0: ?object, 1: array, 2: array}
     */
    public function hydrate(Heap $heap, array $row): array
    {
        // PK identity from a list loop (scalar col names, no map walk).
        $pkCols = $this->pkColumns;
        $id     = [];
        $i      = 0;
        foreach ($pkCols as $col) {
            $value = $row[$col] ?? null;
            if ($value === null) {
                return [null, [], []]; // orphan guard
            }
            $id[$this->pkFields[$i++]] = $value;
        }

        // Identity hit: same PK in one request = same instance.
        $existing = $heap->findById($this->class, $id);
        if ($existing !== null) {
            $entity = $heap->entityFor($existing);
            if ($entity !== null) {
                return [$entity, $id, $existing->data];
            }
        }

        $entity = new ($this->class)();

        // Copy loop 1: field assignment from raw columns (paired lists).
        $fields = $this->fields;
        $cols   = $this->columns;
        for ($j = 0, $n = \count($fields); $j < $n; $j++) {
            if (array_key_exists($cols[$j], $row)) {
                $entity->{$fields[$j]} = $row[$cols[$j]];
            }
        }

        // Copy loop 2: raw snapshot for the Node.
        $data = [];
        for ($j = 0, $n = \count($fields); $j < $n; $j++) {
            $data[$cols[$j]] = $row[$cols[$j]] ?? null;
        }

        $this->attach($heap, $entity, $id, $data);
        return [$entity, $id, $data];
    }

    /**
     * Attach a hydrated entity to the heap as MANAGED.
     */
    public function attach(Heap $heap, object $entity, array $id, array $data): Node
    {
        $node = new Node($this->class, $id, $data, Node::MANAGED);
        $heap->attach($entity, $node);
        return $node;
    }

    /**
     * Forget all compiled plans (tests).
     */
    public static function clear(): void
    {
        self::$plans = [];
        self::$instances = [];
    }
}