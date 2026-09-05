# 🧩 Class: Node

**Full name:** [Azera\Orm\Node](../../src/Orm/Node.php)

A single entity's bookkeeping entry in the [`Heap`](Orm_Heap.md).

Mirrors Cycle's Node concept, trimmed to what the EntityManager's write
pipeline needs:
the entity reference, its identity (PK values), a data snapshot for
dirty diffing, and the persistence lifecycle state.

The data array holds SCALAR row values only (the raw store representation) —
not PHP objects. Objects would break both the diff and the L2 cache story.

## 📌 Public Constants

- **NEW** = `1`
- **MANAGED** = `2`
- **SCHEDULED_INSERT** = `3`
- **SCHEDULED_UPDATE** = `4`
- **SCHEDULED_DELETE** = `5`
- **DELETED** = `6`

## 🌍 Public Properties

- `public readonly` string `$class` · [source](../../src/Orm/Node.php)
- `public readonly` array `$id` · [source](../../src/Orm/Node.php)
- `public` array `$data` · [source](../../src/Orm/Node.php)
- `public` int `$state` · [source](../../src/Orm/Node.php)
- `public` array `$changedFields` · [source](../../src/Orm/Node.php)

## 🚀 Public methods

### __construct() · [source](../../src/Orm/Node.php#L36)

`public function __construct(string $class, array $id, array $data, int $state = 1, array $changedFields = []): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$class` | string | - |  |
| `$id` | array | - |  |
| `$data` | array | - |  |
| `$state` | int | `1` |  |
| `$changedFields` | array | `[]` |  |

**➡️ Return value**

- Type: mixed


---

### isScheduled() · [source](../../src/Orm/Node.php#L46)

`public function isScheduled(): bool`

**➡️ Return value**

- Type: bool



---

[Back to the Index ⤴](README.md)
