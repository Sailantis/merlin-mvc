# 🧩 Class: MappingResolver

**Full name:** [Azera\Db\Resolver\MappingResolver](../../src/Db/Resolver/MappingResolver.php)

Resolves logical names via a [`ModelMapping`](Core_ModelMapping.md) configuration.

Each entry in the mapping provides a `source` (table name), optional
`schema`, and optional connection roles (`connection` for both read+write,
or individual `read`/`write` overrides). No model hydration is available —
`modelClass` and `idFields` are always null.

## 🚀 Public methods

### __construct() · [source](../../src/Db/Resolver/MappingResolver.php#L19)

`public function __construct(Azera\Core\ModelMapping $mapping): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$mapping` | [ModelMapping](Core_ModelMapping.md) | - |  |

**➡️ Return value**

- Type: mixed


---

### resolve() · [source](../../src/Db/Resolver/MappingResolver.php#L23)

`public function resolve(string $name): array`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | - |  |

**➡️ Return value**

- Type: array



---

[Back to the Index ⤴](README.md)
