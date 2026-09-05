# 🧩 Class: FastHydrator

**Full name:** [Azera\Orm\FastHydrator](../../src/Orm/FastHydrator.php)

Per-class compiled hydration plan.

The generic HydrationMap + RowSplitter path re-walks the metadata arrays
for EVERY row and entity: `foreach ($meta['columns'] as ...)` twice plus
per-field array_key_exists checks. This class compiles the same plan into
flat scalar arrays (field names, column aliases, PK aliases) ONCE per
class, so hydrating a row is: one heap lookup, one instantiation, two
tightly-typed copy loops over plain lists — no metadata array walking.

Same contract as HydrationMap::build() + RowSplitter::split() for the
single-root (no relations) case, which is the hot path for list reads.
Relations keep using the generic path (they are per-row by nature).

L1-cached per class like Metadata; nothing else to configure.

## 🌍 Public Properties

- `public` string `$class` · [source](../../src/Orm/FastHydrator.php)
- `public` array `$fields` · [source](../../src/Orm/FastHydrator.php)
- `public` array `$columns` · [source](../../src/Orm/FastHydrator.php)
- `public` array `$pkFields` · [source](../../src/Orm/FastHydrator.php)
- `public` array `$pkColumns` · [source](../../src/Orm/FastHydrator.php)

## 🚀 Public methods

### for() · [source](../../src/Orm/FastHydrator.php#L71)

`public static function for(string $class): self`

Per-class singleton plan (mirrors Metadata::for semantics).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |

**➡️ Return value**

- Type: self


---

### hydrate() · [source](../../src/Orm/FastHydrator.php#L95)

`public function hydrate(Azera\Orm\Heap $heap, array $row): array`

Compile a row -> [entity, id, snapshotData] triple.

Identity-map probe FIRST: with the shared request-scoped heap, the
same row read twice in one request MUST yield the same object (a
per-query heap never faced this because it died with the query).
A hit returns the existing instance untouched — the heap snapshot
stays authoritative and in-request mutations are not clobbered.

Cold path: build id + entity + snapshot in three tight list loops,
attach once.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$heap` | [Heap](Orm_Heap.md) | - |  |
| `$row` | array | - | raw assoc row keyed by COLUMN name |

**➡️ Return value**

- Type: array


---

### attach() · [source](../../src/Orm/FastHydrator.php#L164)

`public function attach(Azera\Orm\Heap $heap, object $entity, array $id, array $data): Azera\Orm\Node`

Attach a hydrated entity to the heap as MANAGED.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$heap` | [Heap](Orm_Heap.md) | - |  |
| `$entity` | object | - |  |
| `$id` | array | - |  |
| `$data` | array | - |  |

**➡️ Return value**

- Type: [Node](Orm_Node.md)


---

### clear() · [source](../../src/Orm/FastHydrator.php#L174)

`public static function clear(): void`

Forget all compiled plans (tests).

**➡️ Return value**

- Type: void



---

[Back to the Index ⤴](README.md)
