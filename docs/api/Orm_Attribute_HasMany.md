# 🧩 Class: HasMany

**Full name:** [Azera\Orm\Attribute\HasMany](../../src/Orm/Attribute/HasMany.php)

One-to-many: the TARGET model's table holds the foreign key back to this
model. Property name = relation name (usually plural).

Defaults: foreignKey = snake_case of THIS class's short name + '_id' on
the TARGET table, ownerKey = this model's first ID field.

Default load strategy: SECOND QUERY by parent IDs (never a JOIN —
joined to-many duplicates parent rows).

## 🌍 Public Properties

- `public` string `$target` · [source](../../src/Orm/Attribute/HasMany.php)
- `public` string|null `$foreignKey` · [source](../../src/Orm/Attribute/HasMany.php)
- `public` string|null `$ownerKey` · [source](../../src/Orm/Attribute/HasMany.php)

## 🚀 Public methods

### __construct() · [source](../../src/Orm/Attribute/HasMany.php#L18)

`public function __construct(string $target, string|null $foreignKey = null, string|null $ownerKey = null): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$target` | string | - |  |
| `$foreignKey` | string\|null | `null` |  |
| `$ownerKey` | string\|null | `null` |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
