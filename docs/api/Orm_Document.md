# 🧩 Class: Document

**Full name:** [Azera\Orm\Document](../../src/Orm/Document.php)

Base class for MongoDB-backed objects (pairs with #[Document]).

FACADE over the [`EntityManager`](Orm_EntityManager.md): save()/delete() delegate to the
EM's write pipeline (persist + flush), so documents and SQL models go
through the SAME diff -> order -> transaction path and land in the SAME
request-scoped heap. The #[Document] attribute's storeRole selects the
StoreManager role (MongoStore once it lands; tenancy via per-tenant
roles).

The EM's heap-node diff is authoritative — hydrated documents are
heap-tracked, so persist() schedules an UPDATE only when fields actually
changed.

## 🚀 Public methods

### storeRole() · [source](../../src/Orm/Document.php#L28)

`public function storeRole(): string`

Which StoreManager role resolves the backend for this document.

Mirrors the #[Document(storeRole: ...)] attribute; the attribute is
the authority when both are present (it compiles into metadata).

**➡️ Return value**

- Type: string


---

### save() · [source](../../src/Orm/Document.php#L39)

`public function save(): bool`

Save via the EM: INSERT when untracked, diff-UPDATE when managed.

The EM's heap-node diff is authoritative (hydrated documents are
heap-tracked with a baseline snapshot), so we schedule FIRST,
check whether anything was actually queued, and only then flush.

**➡️ Return value**

- Type: bool


---

### delete() · [source](../../src/Orm/Document.php#L54)

`public function delete(): bool`

**➡️ Return value**

- Type: bool


---

### hasChanged() · [source](../../src/Orm/Document.php#L86)

`public function hasChanged(): bool`

Whether any field differs from the heap baseline (untracked entity:
true when any metadata column has a set value).

**➡️ Return value**

- Type: bool


---

### changedData() · [source](../../src/Orm/Document.php#L97)

`public function changedData(): array`

Field-name-keyed map of values that differ from the heap baseline
(untracked entity: all set values).

**➡️ Return value**

- Type: array


---

### loadState() · [source](../../src/Orm/Document.php#L106)

`public function loadState(): static`

Revert all properties to the values recorded in the heap node
snapshot (the loadState() replacement). No-op for untracked entities.

**➡️ Return value**

- Type: static



---

[Back to the Index ⤴](README.md)
