# 🧩 Class: StoreManager

**Full name:** [Azera\Orm\Storage\StoreManager](../../src/Orm/Storage/StoreManager.php)

Service registry: role -> Store instance, SPLIT BY STORE TYPE. SQL stores
and mongo stores live in separate maps — a #[Document] class must never
resolve the SQL PdoStore (its SQL-shaped fallback would silently write a
table). Owns NO connections — SQL connections stay in DatabaseManager
(PdoStore borrows them via its read/write roles); MongoStore wraps the
Mongo driver directly.

RequestScoped: registry entries are config-like, but the request-scoped
reset keeps tests clean and lets tenant config swap safely in workers.

## 🚀 Public methods

### set() · [source](../../src/Orm/Storage/StoreManager.php#L32)

`public function set(string $role, Azera\Orm\Storage\Store|callable $store): static`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - |  |
| `$store` | [Store](Orm_Storage_Store.md)\|callable | - |  |

**➡️ Return value**

- Type: static


---

### setMongo() · [source](../../src/Orm/Storage/StoreManager.php#L43)

`public function setMongo(string $role, Azera\Orm\Storage\Store|callable $store): static`

Register a Store for a mongo role (resolves #[Document(storeRole)]).

A plain set() entry with the same name stays a SEPARATE SQL entry —
the two maps never mix.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - |  |
| `$store` | [Store](Orm_Storage_Store.md)\|callable | - |  |

**➡️ Return value**

- Type: static


---

### setDefault() · [source](../../src/Orm/Storage/StoreManager.php#L49)

`public function setDefault(string $role): static`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - |  |

**➡️ Return value**

- Type: static


---

### setMongoDefault() · [source](../../src/Orm/Storage/StoreManager.php#L55)

`public function setMongoDefault(string $role): static`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - |  |

**➡️ Return value**

- Type: static


---

### has() · [source](../../src/Orm/Storage/StoreManager.php#L61)

`public function has(string $role): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - |  |

**➡️ Return value**

- Type: bool


---

### hasMongo() · [source](../../src/Orm/Storage/StoreManager.php#L66)

`public function hasMongo(string $role): bool`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - |  |

**➡️ Return value**

- Type: bool


---

### get() · [source](../../src/Orm/Storage/StoreManager.php#L71)

`public function get(string $role): Azera\Orm\Storage\Store`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - |  |

**➡️ Return value**

- Type: [Store](Orm_Storage_Store.md)


---

### getMongo() · [source](../../src/Orm/Storage/StoreManager.php#L81)

`public function getMongo(string $role): Azera\Orm\Storage\Store`

Resolve a MONGO role or fall back to the mongo default. Never touches
the SQL map (no cross-type fallback) and never falls back to a global
'default' — an unconfigured mongo role throws.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - |  |

**➡️ Return value**

- Type: [Store](Orm_Storage_Store.md)


---

### getOrDefault() · [source](../../src/Orm/Storage/StoreManager.php#L89)

`public function getOrDefault(string $role): Azera\Orm\Storage\Store`

Resolve a role or fall back to the default.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$role` | string | - |  |

**➡️ Return value**

- Type: [Store](Orm_Storage_Store.md)


---

### roles() · [source](../../src/Orm/Storage/StoreManager.php#L102)

`public function roles(): array`

All registered roles (SQL + mongo, distinct namespaces not merged).

**➡️ Return value**

- Type: array


---

### mongoRoles() · [source](../../src/Orm/Storage/StoreManager.php#L111)

`public function mongoRoles(): array`

All registered mongo roles.

**➡️ Return value**

- Type: array


---

### defaultRole() · [source](../../src/Orm/Storage/StoreManager.php#L116)

`public function defaultRole(): string|null`

**➡️ Return value**

- Type: string|null


---

### mongoDefaultRole() · [source](../../src/Orm/Storage/StoreManager.php#L121)

`public function mongoDefaultRole(): string|null`

**➡️ Return value**

- Type: string|null


---

### resetState() · [source](../../src/Orm/Storage/StoreManager.php#L126)

`public function resetState(): void`

**➡️ Return value**

- Type: void



---

[Back to the Index ⤴](README.md)
