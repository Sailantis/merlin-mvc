# 🧩 Class: Paginator

**Full name:** [Azera\Db\Paginator](../../src/Db/Paginator.php)

Paginator class for paginating database query results.

## 🚀 Public methods

### __construct() · [source](../../src/Db/Paginator.php#L34)

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

### reverse() · [source](../../src/Db/Paginator.php#L52)

`public function reverse(bool $reverse = true): static`

Set whether to reverse the order of items.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$reverse` | bool | `true` | True to reverse the order, false otherwise. |

**➡️ Return value**

- Type: static


---

### models() · [source](../../src/Db/Paginator.php#L66)

`public function models(): array`

Execute and return items as hydrated model instances.

Requires the query to have a model bound (e.g. via Item::query()).
Each row is fetched as a model instance with saveState() called.

**➡️ Return value**

- Type: array


---

### objects() · [source](../../src/Db/Paginator.php#L77)

`public function objects(): array`

Execute and return items as plain stdClass objects.

No model hydration — rows are fetched directly via PDO::FETCH_OBJ.
Table resolution and relations still go through the model.

**➡️ Return value**

- Type: array


---

### assoc() · [source](../../src/Db/Paginator.php#L87)

`public function assoc(): array`

Execute and return items as associative arrays.

No model hydration — rows are fetched directly via PDO::FETCH_ASSOC.

**➡️ Return value**

- Type: array


---

### fetch() · [source](../../src/Db/Paginator.php#L97)

`public function fetch(mixed $fetchMode = 0): array`

Execute and return items using the PDO default fetch mode.

Backward-compatible with the original execute() API.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$fetchMode` | mixed | `0` |  |

**➡️ Return value**

- Type: array


---

### items() · [source](../../src/Db/Paginator.php#L156)

`public function items(): array|null`

Get the items for the current page. Return null if the query has not been executed yet.

**➡️ Return value**

- Type: array|null
- Description: The items for the current page, or null if the query has not been executed yet.


---

### totalItems() · [source](../../src/Db/Paginator.php#L166)

`public function totalItems(): int`

Get the total number of items across all pages.

**➡️ Return value**

- Type: int
- Description: The total number of items.


---

### firstItem() · [source](../../src/Db/Paginator.php#L176)

`public function firstItem(): int`

Get the position of the first item in the current page (1-based index).

**➡️ Return value**

- Type: int
- Description: The position of the first item in the current page.


---

### lastItem() · [source](../../src/Db/Paginator.php#L186)

`public function lastItem(): int`

Get the position of the last item in the current page (1-based index).

**➡️ Return value**

- Type: int
- Description: The position of the last item in the current page.


---

### currentPage() · [source](../../src/Db/Paginator.php#L196)

`public function currentPage(): int`

Get the current page number.

**➡️ Return value**

- Type: int
- Description: The current page number.


---

### pageSize() · [source](../../src/Db/Paginator.php#L206)

`public function pageSize(): int`

Get the page size (number of items per page).

**➡️ Return value**

- Type: int
- Description: The page size.


---

### previousPage() · [source](../../src/Db/Paginator.php#L216)

`public function previousPage(): int`

Get the previous page number.

**➡️ Return value**

- Type: int
- Description: The previous page number.


---

### nextPage() · [source](../../src/Db/Paginator.php#L226)

`public function nextPage(): int`

Get the next page number.

**➡️ Return value**

- Type: int
- Description: The next page number.


---

### hasPrevious() · [source](../../src/Db/Paginator.php#L236)

`public function hasPrevious(): bool`

Check if there is a previous page.

**➡️ Return value**

- Type: bool
- Description: True if there is a previous page, false otherwise.


---

### hasNext() · [source](../../src/Db/Paginator.php#L246)

`public function hasNext(): bool`

Check if there is a next page.

**➡️ Return value**

- Type: bool
- Description: True if there is a next page, false otherwise.


---

### lastPage() · [source](../../src/Db/Paginator.php#L256)

`public function lastPage(): int`

Get the last page number.

**➡️ Return value**

- Type: int
- Description: The last page number.



---

[Back to the Index ⤴](README.md)
