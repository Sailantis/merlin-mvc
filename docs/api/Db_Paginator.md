# 🧩 Class: Paginator

**Full name:** [Azera\Db\Paginator](../../src/Db/Paginator.php)

Paginator class for paginating database query results.

## 🚀 Public methods

### __construct() · [source](../../src/Db/Paginator.php#L30)

`public function __construct(Azera\Db\Query $builder, int $page = 1, int $pageSize = 30, bool $reverse = false): mixed`

Create a new Paginator instance.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$builder` | [Query](Db_Query.md) | - | The Query builder instance to paginate. |
| `$page` | int | `1` | The current page number. |
| `$pageSize` | int | `30` | The number of items per page. |
| `$reverse` | bool | `false` | Whether to reverse the order of items. |

**➡️ Return value**

- Type: mixed


---

### execute() · [source](../../src/Db/Paginator.php#L48)

`public function execute(mixed $fetchMode = 0): array`

Execute the paginated query and return the items for the current page.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$fetchMode` | mixed | `0` | The \PDO fetch mode to use (default: \PDO::FETCH_DEFAULT). |

**➡️ Return value**

- Type: array
- Description: The items for the current page.


---

### get() · [source](../../src/Db/Paginator.php#L93)

`public function get(): array|null`

Get the items for the current page. Return null if the query has not been executed yet.

**➡️ Return value**

- Type: array|null
- Description: The items for the current page, or null if the query has not been executed yet.


---

### totalItems() · [source](../../src/Db/Paginator.php#L103)

`public function totalItems(): int`

Get the total number of items across all pages.

**➡️ Return value**

- Type: int
- Description: The total number of items.


---

### firstItem() · [source](../../src/Db/Paginator.php#L113)

`public function firstItem(): int`

Get the position of the first item in the current page (1-based index).

**➡️ Return value**

- Type: int
- Description: The position of the first item in the current page.


---

### lastItem() · [source](../../src/Db/Paginator.php#L123)

`public function lastItem(): int`

Get the position of the last item in the current page (1-based index).

**➡️ Return value**

- Type: int
- Description: The position of the last item in the current page.


---

### currentPage() · [source](../../src/Db/Paginator.php#L133)

`public function currentPage(): int`

Get the current page number.

**➡️ Return value**

- Type: int
- Description: The current page number.


---

### pageSize() · [source](../../src/Db/Paginator.php#L143)

`public function pageSize(): int`

Get the page size (number of items per page).

**➡️ Return value**

- Type: int
- Description: The page size.


---

### previousPage() · [source](../../src/Db/Paginator.php#L153)

`public function previousPage(): int`

Get the previous page number.

**➡️ Return value**

- Type: int
- Description: The previous page number.


---

### nextPage() · [source](../../src/Db/Paginator.php#L163)

`public function nextPage(): int`

Get the next page number.

**➡️ Return value**

- Type: int
- Description: The next page number.


---

### hasPrevious() · [source](../../src/Db/Paginator.php#L173)

`public function hasPrevious(): bool`

Check if there is a previous page.

**➡️ Return value**

- Type: bool
- Description: True if there is a previous page, false otherwise.


---

### hasNext() · [source](../../src/Db/Paginator.php#L183)

`public function hasNext(): bool`

Check if there is a next page.

**➡️ Return value**

- Type: bool
- Description: True if there is a next page, false otherwise.


---

### lastPage() · [source](../../src/Db/Paginator.php#L194)

`public function lastPage(): int`

Get the last page number.

**➡️ Return value**

- Type: int
- Description: The last page number.



---

[Back to the Index ⤴](README.md)
