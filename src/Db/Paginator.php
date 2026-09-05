<?php
namespace Azera\Db;

/**
 * Paginator class for paginating database query results.
 *
 * @template T of Model
 */
class Paginator
{
    protected Query $builder;
    protected int $pageSize;
    protected int $page;
    protected bool $reverse;

    protected int $totalItems = 0;
    protected int $lastPage = 1;
    protected int $firstItem = 0;
    protected int $lastItem = 0;
    protected int $previousPage = 0;
    protected int $nextPage = 0;

    /** @var list<T>|null */
    protected ?array $items = null;

    /**
     * Create a new Paginator instance.
     *
     * @param Query<T> $builder The Query builder instance to paginate.
     * @param int $page The current page number.
     * @param int $pageSize The number of items per page.
     * @param bool $reverse Whether to reverse the order of items.
     */
    public function __construct(
        Query $builder,
        int $page = 1,
        int $pageSize = 30,
        bool $reverse = false
    ) {
        $this->builder  = $builder;
        $this->pageSize = max(1, $pageSize);
        $this->page     = max(1, $page);
        $this->reverse  = $reverse;
    }

    /**
     * Set whether to reverse the order of items.
     *
     * @param bool $reverse True to reverse the order, false otherwise.
     * @return static<T>
     */
    public function reverse(bool $reverse = true): static
    {
        $this->reverse = $reverse;
        return $this;
    }

    /**
     * Execute and return items as heap-tracked model instances.
     *
     * Requires the query to have a model bound (e.g. via Item::query()).
     * Hydrates through the ORM (FastHydrator + request-scoped heap) — the
     * same identity-mapped path as Model::find()/entities().
     *
     * @return list<T>
     */
    public function entities(): array
    {
        return $this->run(fn(Query $q) => $q->entities());
    }

    /**
     * Execute and return items as plain stdClass objects.
     *
     * No entity hydration — rows are fetched directly via PDO::FETCH_OBJ.
     * Table resolution and relations still go through the model.
     */
    public function objects(): array
    {
        return $this->run(fn(Query $q) => $q->select()->fetchAll(\PDO::FETCH_OBJ));
    }

    /**
     * Execute and return items as associative arrays.
     *
     * No entity hydration — rows are fetched directly via PDO::FETCH_ASSOC.
     */
    public function assoc(): array
    {
        return $this->run(fn(Query $q) => $q->select()->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Execute and return items using the PDO default fetch mode.
     *
     * Backward-compatible with the original execute() API.
     */
    public function fetch($fetchMode = \PDO::FETCH_DEFAULT): array
    {
        return $this->run(fn(Query $q) => $q->select()->fetchAll($fetchMode));
    }

    /**
     * Run the paginated query: COUNT first, then LIMIT/OFFSET applied and
     * rows extracted by the given callback (which executes the terminal —
     * entities()/select() — exactly once). Shared by entities(), objects(),
     * assoc(), and fetch().
     *
     * @return array Shape matches the fetch closure (list<T> for entities()).
     */
    protected function run(\Closure $fetch): array
    {
        $this->totalItems = $this->builder->count();
        $this->lastPage   = $this->pageSize
            ? (int) \ceil($this->totalItems / $this->pageSize)
            : 1;

        $this->previousPage = \max(1, $this->page - 1);
        $this->nextPage     = \min($this->lastPage, $this->page + 1);

        $offset      = ($this->page - 1) * $this->pageSize;
        $queryLimit  = $this->pageSize;
        $queryOffset = $offset;

        if ($this->reverse) {
            $queryOffset = $this->totalItems - $offset - $this->pageSize;
            if ($queryOffset < 0) {
                $queryLimit += $queryOffset;
                $queryOffset = 0;
            }
        }

        $this->items = [];

        if ($this->page <= $this->lastPage) {
            $query = $this->builder->limit($queryLimit, $queryOffset);

            $this->items = $fetch($query);

            if ($this->reverse) {
                $this->items = \array_reverse($this->items);
            }
        }

        $this->firstItem = $offset + 1;
        $this->lastItem  = $offset + \count($this->items);

        return $this->items;
    }

    /**
     * Get the items for the current page. Return null if the query has not been executed yet.
     *
     * @return list<T>|array|null The items for the current page, or null if the query has not been executed yet.
     */
    public function items(): ?array
    {
        return $this->items;
    }

    /**
     * Get the total number of items across all pages.
     *
     * @return int The total number of items.
     */
    public function totalItems(): int
    {
        return $this->totalItems;
    }

    /**
     * Get the position of the first item in the current page (1-based index).
     *
     * @return int The position of the first item in the current page.
     */
    public function firstItem(): int
    {
        return $this->firstItem;
    }

    /**
     * Get the position of the last item in the current page (1-based index).
     *
     * @return int The position of the last item in the current page.
     */
    public function lastItem(): int
    {
        return $this->lastItem;
    }

    /**
     * Get the current page number.
     *
     * @return int The current page number.
     */
    public function currentPage(): int
    {
        return $this->page;
    }

    /**
     * Get the page size (number of items per page).
     *
     * @return int The page size.
     */
    public function pageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Get the previous page number.
     *
     * @return int The previous page number.
     */
    public function previousPage(): int
    {
        return $this->previousPage;
    }

    /**
     * Get the next page number.
     *
     * @return int The next page number.
     */
    public function nextPage(): int
    {
        return $this->nextPage;
    }

    /**
     * Check if there is a previous page.
     *
     * @return bool True if there is a previous page, false otherwise.
     */
    public function hasPrevious(): bool
    {
        return $this->previousPage >= 1;
    }

    /**
     * Check if there is a next page.
     *
     * @return bool True if there is a next page, false otherwise.
     */
    public function hasNext(): bool
    {
        return $this->nextPage <= $this->lastPage;
    }

    /**
     * Get the last page number.
     *
     * @return int The last page number.
     */
    public function lastPage(): int
    {
        return $this->lastPage;
    }

}