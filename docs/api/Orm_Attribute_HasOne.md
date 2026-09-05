# 🧩 Class: HasOne

**Full name:** [Azera\Orm\Attribute\HasOne](../../src/Orm/Attribute/HasOne.php)

One-to-one: the TARGET model's table holds the foreign key back to this model.

Property name = relation name. Defaults: foreignKey = snake_case of THIS
class's short name + '_id' on the TARGET table, ownerKey = this model's
first ID field.

Default load strategy: SQL JOIN (to-one is always JOIN).

## 🌍 Public Properties

- `public` string `$target` · [source](../../src/Orm/Attribute/HasOne.php)
- `public` string|null `$foreignKey` · [source](../../src/Orm/Attribute/HasOne.php)
- `public` string|null `$ownerKey` · [source](../../src/Orm/Attribute/HasOne.php)

## 🚀 Public methods

### __construct() · [source](../../src/Orm/Attribute/HasOne.php#L17)

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
