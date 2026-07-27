# Models & ORM

**Work with database records as objects** - Discover Azera's Active Record implementation for elegant database interactions. Learn about model configuration, static query helpers, CRUD operations, state tracking, and read/write connections.

Azera models use an Active Record style API backed by `Azera\Db\Query`.

---

## Define a Model

Extend `Azera\Core\Model` and declare public properties for your table columns. No registration or mapping is needed — Azera infers the table name from the class name automatically.

```php
<?php
namespace App\Models;

use Azera\Core\Model;

class User extends Model
{
    public int $id;
    public string $username;
    public string $email;
    public string $status = 'active';
}
```

### Overrideable Methods

| Method              | Default               | Purpose                      |
| ------------------- | --------------------- | ---------------------------- |
| `source(): string`  | snake_case class name | Table or view name           |
| `schema(): ?string` | `null`                | Database schema (PostgreSQL) |
| `idFields(): array` | `['id']`              | Primary key field(s)         |

```php
class OrderItem extends Model
{
    public function source(): string   { return 'order_items'; }
    public function schema(): ?string  { return 'sales'; }
    public function idFields(): array  { return ['order_id', 'product_id']; }
}
```

### Table Name Conventions

By default, class names are converted to snake_case (`AdminUser` → `admin_user`). Enable automatic pluralization globally:

```php
use Azera\Core\ModelMapping;

ModelMapping::usePluralTableNames(true);
// User → users, AdminUser → admin_users, Person → people
```

Irregular plurals (`person` → `people`) are handled. Override `source()` on any model to bypass the convention entirely.

> **Note:** Model has no `toArray()` method. Access properties directly or build an array manually: `['id' => $user->id, 'email' => $user->email]`.

---

## Query Builder

`Model::query()` returns a `Query` builder pre-scoped to the model's table and read connection. Use it for anything beyond simple lookups.

```php
// Optional table alias
$activeUsers = User::query('u')
    ->where('u.status', 'active')
    ->orderBy('u.created_at DESC')
    ->limit(20)
    ->select();
```

See [Database Queries](05-DATABASE-QUERIES.md) for the full query builder API.

---

## Static Load Helpers

All static helpers return fully hydrated model instances with state tracking already established.

```php
$user  = User::find(123);                           // ?static by primary key
$user  = User::findOrFail(123);                     // static or throws Exception
$user  = User::findOne(['email' => $email]);        // ?static, first match
$users = User::findAll(['status' => 'active']);     // ResultSet<static>

$exists = User::exists(['email' => $email]);        // bool
$count  = User::count(['status' => 'active']);      // int
```

### Composite Keys

```php
// Positional (order matches idFields())
$item = UserProduct::find([10, 25]);

// Named (safer, order-independent)
$item = UserProduct::find(['user_id' => 10, 'product_id' => 25]);
```

---

## Creating Records

### `create()` — insert and return

```php
$user = User::create([
    'username' => 'alice',
    'email'    => 'alice@example.com',
]);
// $user->id is populated after insert (auto-increment or RETURNING)
```

### `forceCreate()` — bypass ID guards

`forceCreate()` inserts all provided values as-is, including manually set ID fields. Useful for seeding or migrations.

```php
$user = User::forceCreate([
    'id'       => 42,
    'username' => 'seeded',
    'email'    => 'seed@example.com',
]);
```

### `firstOrCreate()` — find or insert

```php
$user = User::firstOrCreate(
    ['email' => 'john@example.com'],   // conditions to find by
    ['username' => 'john']              // extra values if creating
);
```

### `updateOrCreate()` — find, update or insert

```php
$user = User::updateOrCreate(
    ['email' => 'john@example.com'],   // conditions to find by
    ['username' => 'johnny']            // values to set on update or merge on create
);
```

---

## Saving Changes

### `save()` — smart INSERT or UPDATE

`save()` inspects the model's state and decides automatically:

- If **all ID fields are set** → `UPDATE` (only changed fields are sent)
- If **any ID field is missing** → `INSERT` (or upsert when there is a conflict key)

Returns `false` when there is nothing to save (no changes detected).

```php
$user = User::find(123);
$user->email = 'new@example.com';
$user->save(); // UPDATE users SET email = ? WHERE id = 123
```

```php
$user = new User();
$user->username = 'bob';
$user->email = 'bob@example.com';
$user->save(); // INSERT INTO users ...
// $user->id is set after insert
```

### `insert()` — always INSERT

Forces an `INSERT` regardless of whether ID fields are set. All non-internal properties are included.

```php
$user->insert();
```

### `update()` — always UPDATE

Sends only the fields that changed since the last `saveState()`. Returns `false` if nothing changed.

```php
$user->email = 'updated@example.com';
$user->update(); // UPDATE users SET email = ? WHERE id = ?
```

### `delete()`

```php
$user->delete(); // DELETE FROM users WHERE id = ?
```

---

## State Tracking

Every model returned by a static helper has its state snapshot automatically saved. This enables change detection and rollback.

| Method         | Description                                               |
| -------------- | --------------------------------------------------------- |
| `saveState()`  | Snapshot the current field values as the baseline         |
| `loadState()`  | Restore all fields to the last snapshot                   |
| `getState()`   | Return the snapshot object (`?static`), or `null` if none |
| `hasChanged()` | `true` if any field differs from the snapshot             |

```php
$user = User::find(123);         // snapshot saved automatically
$user->email = 'new@example.com';

$user->hasChanged();             // true
$user->getState()->email;        // original value

$user->loadState();              // revert to snapshot
$user->hasChanged();             // false

$user->email = 'other@example.com';
if ($user->hasChanged()) {
    $user->update();             // UPDATE only the changed fields
}
```

Properties whose names start with `__` are considered internal and are never included in state comparisons or write operations.

---

## Read/Write Connections

Connections are managed by `DatabaseManager` using named **roles**. Register them in your bootstrap:

```php
use Azera\AppContext;
use Azera\Db\Database;

$mgr = AppContext::instance()->dbManager();
$mgr->set('write', new Database('mysql:host=primary;dbname=myapp', 'rw', 'secret'));
$mgr->set('read',  fn() => new Database('mysql:host=replica;dbname=myapp', 'ro', 'secret'));
```

By default all models read from the `read` role and write to the `write` role, falling back to the registered default when a role is absent.

### Per-model role overrides

```php
// Both read and write to the same custom role
User::setDefaultRole('analytics');

// Fine-grained
User::setDefaultReadRole('replica');
User::setDefaultWriteRole('primary');
```

### Global override (all models)

Call `setDefaultRole()` on the base `Model` class to change the default for every model that has not set its own role:

```php
use Azera\Core\Model;

Model::setDefaultRole('default'); // reset everything to 'default'
```

### Single-database setup

Register one connection under any name — all models fall through to it:

```php
AppContext::instance()->dbManager()->set('default', new Database(...));
```

### Direct connection access

```php
$db = $user->readConnection();   // Database (read role)
$db = $user->writeConnection();  // Database (write role)
```

## Using ModelMapping Without Model Classes

`ModelMapping` lets you query the database using logical model names without defining PHP model classes. This is useful for rapid prototyping, dynamic table mappings, or when you need query-builder convenience for tables that don't warrant a full Active Record class.

### Register a mapping

```php
use Azera\Db\Query;
use Azera\Core\ModelMapping;

$mapping = ModelMapping::fromArray([
    // simple: name => table
    'User'    => 'users',
    // explicit, no schema:
    'Product' => ['source' => 'products'],
    // explicit with schema:
    'Order'   => ['source' => 'orders', 'schema' => 'public'],
]);

Query::setModelMapping($mapping);
Query::useModels(true);
```

Once registered, use the logical name wherever `Query` accepts a table or model reference:

```php
$results = Query::new()
    ->table('User')
    ->where('status', 'active')
    ->select();

// Joins also use logical names
$results = Query::new()
    ->table('User')
    ->join('Order', Condition::new()->where('User.id = Order.user_id'))
    ->columns(['User.id', 'User.email', 'Order.total'])
    ->select();
```

### Auto-generated table names

Pass `true` as the value to let `ModelMapping` derive the table name automatically from the model name (snake_case, or pluralized when `usePluralTableNames` is enabled):

```php
ModelMapping::usePluralTableNames(true); // User → users, AdminUser → admin_users

$mapping = ModelMapping::fromArray([
    'User'    => true,  // auto: "users"
    'Product' => true,  // auto: "products"
]);

Query::setModelMapping($mapping);
Query::useModels(true);
```

### Fluent builder

Use the `add()` method to build mappings programmatically:

```php
$mapping = (new ModelMapping())
    ->add('User', 'users')
    ->add('Order', 'orders', 'public'); // third arg is the schema

Query::setModelMapping($mapping);
```

### Resetting

Pass `null` to remove the mapping and revert to normal model-class resolution:

```php
Query::setModelMapping(null);

// Or disable model mode entirely for literal table-name queries
Query::useModels(false);
```

> **Note:** `ModelMapping` only affects `Query`-level operations. The Active Record helpers (`User::find()`, `User::create()`, etc.) still require a PHP class that extends `Model`.

## Related

- [Database Queries](05-DATABASE-QUERIES.md)
- [Cookbook](10-COOKBOOK.md)
- [API Reference](api/README.md)
