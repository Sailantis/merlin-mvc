# 🧩 Class: Casts

**Full name:** [Azera\Orm\Cast\Casts](../../src/Orm/Cast/Casts.php)

Registry mapping metadata column types to [`Cast`](Orm_Cast_Cast.md) transformations.

The cast key is the COLUMN type declared (or inferred) in metadata —
`#[Column(type: 'json')]` or the property-type inference (array ->
'json', int -> 'int', ...). Inference only guesses the PORTABLE default:
an `array` property on PostgreSQL backed by a native array column must
be declared explicitly as `#[Column(type: 'pgarray')]`.

Registered built-ins (registered in [`Casts::boot()`](Orm_Cast_Casts.md#boot)):

  'int'      decode coerces strings -> int (both property AND snapshot)
  'float'    decode coerces strings -> float (both directions as int)
  'bool'     decode coerces '1'/'0'/'t'/'f'/... -> bool
  'json'     encode json_encode, decode json_decode(..., true)
  'pgarray'  PostgreSQL native array literal <-> 1-D scalar PHP array

Semantics:

- Registered casts apply on BOTH read and write paths; scalar casts
  exist because stringifying drivers (pdo_mysql with emulated prepares,
  pdo_pgsql) return numerics as strings — without them the typed
  property would coerce `int(5)` while the heap snapshot kept `"5"`,
  making diff() schedule a redundant UPDATE for every unchanged numeric
  column on the first persist after hydration.

- Applications can register additional types (encrypted columns, enums,
  money, ...): `Casts::register('encrypted', new EncryptedCast())`.
  Registration before the first Metadata::for() call of the class, or
  Metadata::clear() afterwards — FastHydrator compiles the decode plan
  per class once.

## 🚀 Public methods

### register() · [source](../../src/Orm/Cast/Casts.php#L48)

`public static function register(string $type, Azera\Orm\Cast\Cast $cast): void`

Register (or replace) a cast for a column type.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$type` | string | - |  |
| `$cast` | [Cast](Orm_Cast_Cast.md) | - |  |

**➡️ Return value**

- Type: void


---

### for() · [source](../../src/Orm/Cast/Casts.php#L59)

`public static function for(string $type): Azera\Orm\Cast\Cast|null`

The cast for a column type, or null when the type has no
transformation (values pass through raw in both directions).

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$type` | string | - |  |

**➡️ Return value**

- Type: [Cast](Orm_Cast_Cast.md)|null


---

### types() · [source](../../src/Orm/Cast/Casts.php#L71)

`public static function types(): array`

Registered type names (tests).

**➡️ Return value**

- Type: array


---

### clear() · [source](../../src/Orm/Cast/Casts.php#L81)

`public static function clear(): void`

Drop the registry (tests) — built-ins re-register on next use.

**➡️ Return value**

- Type: void



---

[Back to the Index ⤴](README.md)
