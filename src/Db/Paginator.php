<?php
namespace Merlin\Db;

/**
 * Paginator class for paginating database query results.
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
    protected ?array $items = null;
    protected int $fetchModeCache = 0;

    /** 
     * Create a new Paginator instance.
     *
     * @param Query $builder The Query builder instance to paginate.
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
        $this->builder = $builder;
        $this->pageSize = max(1, $pageSize);
        $this->page = max(1, $page);
        $this->reverse = $reverse;
    }

    /**
     * Get the page size (number of items per page).
     *
     * @return int The page size.
     */
    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Get the total number of items across all pages.
     *
     * @return int The total number of items.
     */
    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    /**
     * Get the last page number.
     *
     * @return int The last page number.
     */
    public function getLastPage(): int
    {
        return $this->lastPage;
    }

    /**
     * Get the current page number.
     *
     * @return int The current page number.
     */
    public function getCurrentPage(): int
    {
        return $this->page;
    }

    /**
     * Get the position of the first item in the current page (1-based index).
     *
     * @return int The position of the first item in the current page.
     */
    public function getFirstItem(): int
    {
        return $this->firstItem;
    }

    /**
     * Get the position of the last item in the current page (1-based index).
     *
     * @return int The position of the last item in the current page.
     */
    public function getLastItem(): int
    {
        return $this->lastItem;
    }

    /**
     * Execute the paginated query and return the items for the current page.
     *
     * @param int $fetchMode The \PDO fetch mode to use (default: \PDO::FETCH_DEFAULT).
     * @return array The items for the current page.
     */
    public function execute($fetchMode = 0): array
    {
        // Count query
        $this->totalItems = $this->builder->count();
        $this->lastPage = $this->pageSize ? (int) ceil($this->totalItems / $this->pageSize) : 1;

        $offset = ($this->page - 1) * $this->pageSize;
        $queryLimit = $this->pageSize;
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
            $this->items = $this->builder
                ->limit($queryLimit, $queryOffset)
                ->select()
                ->fetchAll($fetchMode);

            if ($this->reverse) {
                $this->items = array_reverse($this->items);
            }
        }

        $this->firstItem = $offset + 1;
        $this->lastItem = $offset + \count($this->items);

        return $this->items;
    }

    /**
     * Get the items for the current page. Return null if the query has not been executed yet.
     *
     * @return ?array The items for the current page, or null if the query has not been executed yet.
     */
    public function get(): ?array
    {
        return $this->items;
    }
}
