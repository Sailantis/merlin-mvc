# 🧩 Class: Document

**Full name:** [Azera\Orm\Attribute\Document](../../src/Orm/Attribute/Document.php)

Class-level marker: this object persists to MongoDB as a document.

Presence of this attribute switches the compiled metadata to
store = 'mongo'; the optional argument names the collection
(default: the model's source() name). SQL models simply omit it.

## 🌍 Public Properties

- `public` string|null `$collection` · [source](../../src/Orm/Attribute/Document.php)
- `public` string `$storeRole` · [source](../../src/Orm/Attribute/Document.php)

## 🚀 Public methods

### __construct() · [source](../../src/Orm/Attribute/Document.php#L15)

`public function __construct(string|null $collection = null, string $storeRole = 'default'): mixed`

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$collection` | string\|null | `null` |  |
| `$storeRole` | string | `'default'` |  |

**➡️ Return value**

- Type: mixed



---

[Back to the Index ⤴](README.md)
