# 🧩 Class: BelongsTo

**Full name:** [Azera\Orm\Attribute\BelongsTo](../../src/Orm/Attribute/BelongsTo.php)

Many-to-one: THIS model's table holds the foreign key.

Property name = relation name. Defaults: foreignKey = property name +
'_id' (e.g. property `author` → author_id), ownerKey = target's first
ID field.

Default load strategy: SQL JOIN (to-one is always JOIN).

## 🌍 Public Properties

- `public` string `$target` · [source](../../src/Orm/Attribute/BelongsTo.php)
- `public` string|null `$foreignKey` · [source](../../src/Orm/Attribute/BelongsTo.php)
- `public` string|null `$ownerKey` · [source](../../src/Orm/Attribute/BelongsTo.php)

## 🚀 Public methods

### __construct() · [source](../../src/Orm/Attribute/BelongsTo.php#L17)

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
