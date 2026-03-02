# 🧩 Class: Paginator

**Full name:** [Merlin\Db\Paginator](../../src/Db/Paginator.php)

Paginator class for paginating database query results.

## 🚀 Public methods

### __construct() · [source](../../src/Db/Paginator.php#L29)

`public function __construct(Merlin\Db\Query $builder, int $page = 1, int $pageSize = 30, bool $reverse = false): mixed`

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

### getPageSize() · [source](../../src/Db/Paginator.php#L46)

`public function getPageSize(): int`

Get the page size (number of items per page).

**➡️ Return value**

- Type: int
- Description: The page size.


---

### getTotalItems() · [source](../../src/Db/Paginator.php#L56)

`public function getTotalItems(): int`

Get the total number of items across all pages.

**➡️ Return value**

- Type: int
- Description: The total number of items.


---

### getLastPage() · [source](../../src/Db/Paginator.php#L66)

`public function getLastPage(): int`

Get the last page number.

**➡️ Return value**

- Type: int
- Description: The last page number.


---

### getCurrentPage() · [source](../../src/Db/Paginator.php#L76)

`public function getCurrentPage(): int`

Get the current page number.

**➡️ Return value**

- Type: int
- Description: The current page number.


---

### getFirstItem() · [source](../../src/Db/Paginator.php#L86)

`public function getFirstItem(): int`

Get the position of the first item in the current page (1-based index).

**➡️ Return value**

- Type: int
- Description: The position of the first item in the current page.


---

### getLastItem() · [source](../../src/Db/Paginator.php#L96)

`public function getLastItem(): int`

Get the position of the last item in the current page (1-based index).

**➡️ Return value**

- Type: int
- Description: The position of the last item in the current page.


---

### execute() · [source](../../src/Db/Paginator.php#L107)

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

### get() · [source](../../src/Db/Paginator.php#L149)

`public function get(): array|null`

Get the items for the current page. Return null if the query has not been executed yet.

**➡️ Return value**

- Type: array|null
- Description: The items for the current page, or null if the query has not been executed yet.



---

[Back to the Index ⤴](index.md)
