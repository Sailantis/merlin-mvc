# 🧩 Class: Table

**Full name:** [Azera\Orm\Attribute\Table](../../src/Orm/Attribute/Table.php)

Class-level table configuration for SQL models.

Declares the table name and/or database schema (PostgreSQL et al.)
declaratively instead of overriding source()/schema(). Compiled into
metadata, so the Store seam, the query builder (via ModelResolver) and
the model facade defaults all see it with zero runtime cost.

Precedence: a source()/schema() override on the model still wins over
the attribute (dynamic > static); the attribute wins over the naming
convention.

## 🌍 Public Properties

- `public` string|null `$name` · [source](../../src/Orm/Attribute/Table.php)
- `public` string|null `$schema` · [source](../../src/Orm/Attribute/Table.php)

## 🚀 Public methods

### __construct() · [source](../../src/Orm/Attribute/Table.php#L20)

`public function __construct(string|null $name = null, string|null $schema = null): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string\|null | `null` |  |
| `$schema` | string\|null | `null` |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
