# 🧩 Class: StoreManager

**Full name:** [Azera\Orm\Storage\StoreManager](../../src/Orm/Storage/StoreManager.php)

Service registry: (store type, role) -> Store instance. The type dimension
('sql' | 'mongo') splits the maps — a #[Document] class must never resolve
the SQL PdoStore (its SQL-shaped fallback would silently write a table),
so there is never a cross-type fallback. Owns NO connections — SQL
connections stay in DatabaseManager (PdoStore borrows them via its
read/write roles); MongoStore wraps the Mongo driver directly.

RequestScoped: registry entries are config-like, but the request-scoped
reset keeps tests clean and lets tenant config swap safely in workers.

## 🚀 Public methods

### set() · [source](../../src/Orm/Storage/StoreManager.php#L30)

`public function set(string $type, string $role, Azera\Orm\Storage\Store|callable $store): static`

Register a Store for a (type, role) pair. Types are 'sql' and 'mongo';
an unknown type throws immediately (typo-proof at registration time).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$type` | string | - |  |
| `$role` | string | - |  |
| `$store` | [Store](Orm_Storage_Store.md)\|callable | - |  |

**➡️ Return value**

- Type: static


---

### setDefault() · [source](../../src/Orm/Storage/StoreManager.php#L41)

`public function setDefault(string $type, string $role): static`

Register the default role for a type: getOrDefault(type, missingRole)
falls back to it. get(type, role) stays strict — no default fallback.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$type` | string | - |  |
| `$role` | string | - |  |

**➡️ Return value**

- Type: static


---

### has() · [source](../../src/Orm/Storage/StoreManager.php#L47)

`public function has(string $type, string $role): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$type` | string | - |  |
| `$role` | string | - |  |

**➡️ Return value**

- Type: bool


---

### get() · [source](../../src/Orm/Storage/StoreManager.php#L56)

`public function get(string $type, string $role): Azera\Orm\Storage\Store`

Strict resolution: exact (type, role) entry or an exception — never
the type's default role, never the other type's map.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$type` | string | - |  |
| `$role` | string | - |  |

**➡️ Return value**

- Type: [Store](Orm_Storage_Store.md)


---

### getOrDefault() · [source](../../src/Orm/Storage/StoreManager.php#L65)

`public function getOrDefault(string $type, string $role): Azera\Orm\Storage\Store`

Lenient resolution: exact (type, role) entry, else the type's default
role, else an exception.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$type` | string | - |  |
| `$role` | string | - |  |

**➡️ Return value**

- Type: [Store](Orm_Storage_Store.md)


---

### roles() · [source](../../src/Orm/Storage/StoreManager.php#L74)

`public function roles(string $type): array`

All registered roles for a type.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$type` | string | - |  |

**➡️ Return value**

- Type: array


---

### defaultRole() · [source](../../src/Orm/Storage/StoreManager.php#L79)

`public function defaultRole(string $type): string|null`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$type` | string | - |  |

**➡️ Return value**

- Type: string|null


---

### resetState() · [source](../../src/Orm/Storage/StoreManager.php#L84)

`public function resetState(): void`

**➡️ Return value**

- Type: void



---

[Back to the Index ⤴](README.md)
