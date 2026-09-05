# 🧩 Class: Column

**Full name:** [Azera\Orm\Attribute\Column](../../src/Orm/Attribute/Column.php)

Declares a property as a persistent column.

Everything is optional: an unattributed declared property is still a
column with inferred defaults (name = property name, type 'string').
The attribute exists to override those defaults.

`pk` explicitly marks (or excludes) a primary key: true marks the
column as part of the PK (composite keys = multiple marked columns);
false excludes a column the *_id name convention would wrongly mark.
An idFields() override on the model still wins over these marks.

## 🌍 Public Properties

- `public` string `$type` · [source](../../src/Orm/Attribute/Column.php)
- `public` string|null `$name` · [source](../../src/Orm/Attribute/Column.php)
- `public` bool `$nullable` · [source](../../src/Orm/Attribute/Column.php)
- `public` bool `$transient` · [source](../../src/Orm/Attribute/Column.php)
- `public` bool|null `$pk` · [source](../../src/Orm/Attribute/Column.php)

## 🚀 Public methods

### __construct() · [source](../../src/Orm/Attribute/Column.php#L20)

`public function __construct(string $type = 'string', string|null $name = null, bool $nullable = false, bool $transient = false, bool|null $pk = null): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$type` | string | `'string'` |  |
| `$name` | string\|null | `null` |  |
| `$nullable` | bool | `false` |  |
| `$transient` | bool | `false` |  |
| `$pk` | bool\|null | `null` |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
