# Models & ORM

**Work with database records as objects** - Discover Azera's Active Record implementation for elegant database interactions. Learn about model configuration, static query helpers, CRUD operations, state tracking, and read/write connections.

Azera models use an Active Record style API backed by the
`Azera\Orm\EntityManager` (identity map + write pipeline).

---

## Define a Model

Extend `Azera\Orm\Model` and declare public properties for your table columns. No registration or mapping is needed — Azera infers the table name from the class name automatically.

```php
<?php
namespace App\Models;

use Azera\Orm\Model;

class User extends Model
{
    public int $id;
    public string $username;
    public string $email;
    public string $status = 'active';
}
```

### Declarative Attributes

All model configuration can be declared with attributes, compiled once into
cached metadata:

```php
use Azera\Orm\Model;
use Azera\Orm\Attribute\Column;
use Azera\Orm\Attribute\Connection;
use Azera\Orm\Attribute\Table;

#[Table(name: 'admin_users', schema: 'sales')]
#[Connection(read: 'replica', write: 'primary')]
class AdminUser extends Model
{
    #[Column(type: 'int', pk: true)]
    public $tenant_id;

    #[Column(type: 'int', pk: true)]
    public $user_id;   // composite key = multiple pk marks

    #[Column(name: 'status_code', type: 'int', pk: false)]
    public $status;    // renamed column excluded from the key
}
```

| Attribute                                                | Applies to           | Purpose                         |
| -------------------------------------------------------- | -------------------- | ------------------------------- |
| `#[Table(name:, schema:)]`                               | SQL models           | Table name and database schema  |
| `#[Connection(role:)]` or `#[Connection(read:, write:)]` | SQL models           | Read/write connection roles     |
| `#[Column(type:, name:, nullable:, transient:, pk:)]`    | Any persistent class | Column configuration            |
| `#[Document(collection:, storeRole:)]`                   | Mongo documents      | Store + collection + store role |

`#[Table]` / `#[Connection]` on a `#[Document]` class throw — mongo routes
storage through `storeRole` instead.

### Column Casts (value transformation)

Values with a registered cast type are **encoded before every write** and
**decoded after every read** — the entity property holds the PHP value,
storage holds the raw store representation:

```php
class Article extends Model
{
    /** Portable default (inferred from `array`): JSON in a text/json column. */
    public array $tags;

    /** pg native array column — declared, because inference can't know the schema. */
    #[Column(type: 'pgarray')]
    public array $labels;
}
```

| Type      | Decode (read)                                     | Encode (write)                                |
| --------- | ------------------------------------------------- | --------------------------------------------- |
| `int`     | `"5"` → `5` (stringifying drivers return strings) | passthrough                                   |
| `float`   | `"4.5"` → `4.5`                                   | passthrough                                   |
| `bool`    | `'1'`/`'t'`/`'true'` → `true`, unknown → throw    | passthrough                                   |
| `json`    | JSON text → array (assoc), invalid → throw        | `json_encode`, scalars pass through           |
| `pgarray` | pg array literal → scalar array (nested → nested) | pg literal, nested supported, >6 dims → throw |

Why the scalar casts exist: `pdo_mysql` (emulated prepares) and
`pdo_pgsql` return numerics as strings. Without them the typed property
coerces to `int` while the heap snapshot keeps `"5"` — diffing compares
`int(5) !== "5"` and the first persist of an unchanged entity schedules a
phantom UPDATE per numeric column. The casts coerce **both** the property
and the snapshot so diff compares like with like.

Custom types — implement `Azera\Orm\Cast\Cast` (encode/decode) and
register before first use:

```php
Azera\Orm\Cast\Casts::register('encrypted', new EncryptedCast());
```

Register before the first `Metadata::for()` of the affected class (or call
`Metadata::clear()` after) — the decode plan is compiled per class.

Semantics: the snapshot (`node->data`) always holds the **store
representation** (encoded strings) so the diff engine compares stable
scalars; `dirtyData()` therefore returns encoded values too. Mongo
documents bypass value shaping entirely (BSON owns encoding — `json` is
inert there). `datetime` decode (string → `DateTimeImmutable` on
hydration) is deliberately **not** built-in: it would change the entity
surface for every existing model; register a `Cast` yourself when wanted.

`#[Table]` / `#[Connection]` on a `#[Document]` class throw — mongo routes
storage through `storeRole` instead.

### Mongo Documents (MongoStore)

`#[Document]` classes always route to MongoDB — never the SQL store. The
stack is two layers, not two alternatives: **ext-mongodb** (PECL) is the
driver (wire protocol, BSON) and **mongodb/mongodb** (composer) is the
pure-PHP API on top of it; using documents means using both.

```php
use Azera\Orm\Attribute\Column;
use Azera\Orm\Attribute\Document;
use Azera\Orm\Storage\MongoStore;
use Azera\Orm\Storage\StoreManager;
use MongoDB\Client;

#[Document(collection: 'articles', storeRole: 'mongo')]
class Article extends Document
{
    public $_id;              // mongo's PK; ObjectId string after insert
    public $title;
    #[Column(type: 'json')]
    public $tags;             // arrays pass through raw — BSON owns encoding
}

// bootstrap: register the mongo store for the role
$client = new Client('mongodb://localhost:27017');
$stores->setMongo('mongo', new MongoStore($client, database: 'myapp'));
$stores->setMongoDefault('mongo');

$article = new Article();
$article->title = 'Hello';
$article->tags  = ['php', 'mongo'];
$article->save();                 // INSERT — driver-generated ObjectId backfills _id

$found  = Article::find($article->_id);  // EM find → heap identity
$found->title = 'Edited';
$found->save();                   // $set diff UPDATE (only changed fields)
$found->delete();                 // deleteOne by _id
```

| Piece           | Contract                                                                                |
| --------------- | --------------------------------------------------------------------------------------- |
| Collection name | `#[Document(collection:)]` — falls back to the snake/plural convention                  |
| Primary key     | `_id`; omitted at insert = driver-generated ObjectId, backfilled as string              |
| `_id` filters   | the store casts 24-hex-char string `_id`s back to ObjectId automatically                |
| Values          | arrays/dates pass through raw; the driver maps BSON                                     |
| Transactions    | begin/commit/rollback are no-ops (multi-doc ACID needs replica-set sessions — deferred) |

Store roles are split **per store type** (`setMongo()`/`getMongo()`), so a
document can never resolve the SQL `PdoStore` and vice versa. Documents in a
context without a registered mongo store throw loudly instead of silently
writing a SQL table.

**Primary keys:** a declared `idFields()` override is the authority. Without
one, explicit `#[Column(pk: true)]` marks define the key (composite = several
marks); the residual default is `['id']`. A single explicit mark switches the
whole class off the `id`/`*_id` name convention, so a foreign-key-like `*_id`
column never leaks into the key.

### Overrideable Methods

The methods below remain the dynamic escape hatch — an override wins over
the corresponding attribute:

| Method              | Default                          | Purpose                      |
| ------------------- | -------------------------------- | ---------------------------- |
| `source(): string`  | `#[Table(name)]` / convention    | Table or view name           |
| `schema(): ?string` | `#[Table(schema)]` / `null`      | Database schema (PostgreSQL) |
| `idFields(): array` | `#[Column(pk)]` marks / `['id']` | Primary key field(s)         |

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
use Azera\Db\ModelMapping;

ModelMapping::usePluralTableNames(true);
// User → users, AdminUser → admin_users, Person → people
```

Irregular plurals (`person` → `people`) are handled. Override `source()` on any model to bypass the convention entirely.

> **Note:** Model has no `toArray()` method. Access properties directly or build an array manually: `['id' => $user->id, 'email' => $user->email]`.

### Metadata Cache

Attribute configuration compiles once into a per-process array (L1) that
survives across requests in long-running workers (RoadRunner). For
request-per-process runtimes (PHP-FPM) you can wire an **opt-in second
tier** (L2) backed by any PSR-16 cache:

```php
use Azera\Cache\Backend\ApcuCache;   // azera-cache (Redis, File, … also work)
use Azera\Orm\Metadata;

Metadata::useCache(new ApcuCache(), ttl: 86400);
```

| Method                                  | Purpose                                                                                 |
| --------------------------------------- | --------------------------------------------------------------------------------------- |
| `Metadata::useCache($psr16, ?int $ttl)` | Enable/disable the L2 backend; `$ttl` in seconds (`null` = backend default)             |
| `Metadata::cacheSalt(?string $salt)`    | Mix a value (e.g. deploy hash) into the cache key — changing it forces a full recompile |
| `Metadata::clear()`                     | Reset L1 + delete **only** Azera's L2 keys (never the whole shared segment)             |

```php
// Recommended: tie entries to a deploy so changed model code recompiles
Metadata::cacheSalt($_ENV['DEPLOY_HASH']);
```

> **Important:** once an L2 backend is wired, invalidating it is your
> responsibility — shared cache stores outlive code deploys. Use a
> `cacheSalt()` deploy hash, a TTL, or `Metadata::clear()` on deploy.
> Without a backend there is no L2 at all, and metadata is always
> compiled fresh per process (always correct, microsecond cost).

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
$user  = User::findOrFail(123);                     // static or throws RuntimeException
$user  = User::findOne(['email' => $email]);        // ?static, first match
$users = User::findAll(['status' => 'active']);     // list<static>

$exists = User::exists(['email' => $email]);        // bool
$count  = User::count(['status' => 'active']);      // int
```

All loads go through the `EntityManager`'s identity map: the same row read
twice in one request returns the SAME instance, and loads establish the
heap baseline for change detection.

### Composite Keys

```php
// Positional (order matches idFields())
$item = UserProduct::find([10, 25]);

// Named (safer, order-independent)
$item = UserProduct::find(['user_id' => 10, 'product_id' => 25]);
```

When inserting a model with composite keys, any ID fields that are left unset are
backfilled automatically from the database where the server supports `RETURNING`
(PostgreSQL, MySQL 8.0.27+, MariaDB 10.5.0+, SQLite 3.35+). On older MySQL/MariaDB/SQLite
servers, only a single auto-increment ID field can be backfilled via `lastInsertId()`;
the remaining composite key fields must be set manually before saving.

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

Removed. Use `upsert()` (atomic INSERT ... ON CONFLICT DO UPDATE) when you
control the data, or `create()` when you don't.

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

### `insert()` / `update()` — removed

Removed in favor of ONE write pipeline: `save()` (diff INSERT or UPDATE
through the EntityManager) and `upsert()` (single atomic
INSERT ... ON CONFLICT DO UPDATE statement).

### `delete()`

```php
$user->delete(); // DELETE FROM users WHERE id = ?
```

---

## State Tracking

Every model loaded through a static helper is heap-tracked by the
`EntityManager` — the node snapshot doubles as the diff baseline.

| Method          | Description                                        |
| --------------- | -------------------------------------------------- |
| `hasChanged()`  | `true` if any field differs from the heap baseline |
| `changedData()` | Field-name-keyed map of changed values             |
| `loadState()`   | Restore all fields to the heap baseline            |

```php
$user = User::find(123);         // heap-tracked with baseline
$user->email = 'new@example.com';

$user->hasChanged();             // true
$user->changedData();            // ['email' => 'new@example.com']

$user->loadState();              // revert to baseline
$user->hasChanged();             // false

$user->email = 'other@example.com';
if ($user->hasChanged()) {
    $user->save();               // UPDATE only the changed fields
}
```

The ORM tracks only the columns declared via metadata (public properties /
`#[Column]` attributes); properties that are not part of the model's
metadata are ignored by change detection and writes.

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

Static config: put `#[Connection]` on the class — it sits above the
base-model global override and below runtime setters:

```php
use Azera\Orm\Attribute\Connection;

#[Connection('analytics')]                         // read + write
#[Connection(read: 'replica', write: 'primary')]   // split routing
class User extends Model { ... }
```

Runtime config (per-request tenancy etc.) beats the attribute:

```php
// Both read and write to the same custom role
User::setDefaultRole('analytics');

// Fine-grained
User::setDefaultReadRole('replica');
User::setDefaultWriteRole('primary');
```

### Global override (all models)

Call `setDefaultRole()` on the base `Model` class to change the default for every model that has not set its own role or `#[Connection]` attribute:

```php
use Azera\Orm\Model;

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

In the new resolver system, a `MappingResolver` wraps the mapping and is registered in `AppContext` (typically as part of a `ChainResolver` alongside a `ModelResolver`).

### Register a mapping

```php
use Azera\AppContext;
use Azera\Db\ModelMapping;
use Azera\Db\Resolver\ChainResolver;
use Azera\Db\Resolver\MappingResolver;
use Azera\Db\Resolver\ModelResolver;
use Azera\Db\Resolver\TableResolver;

$mapping = ModelMapping::fromArray([
    // simple: name => table
    'User'    => 'users',
    // explicit, no schema:
    'Product' => ['source' => 'products'],
    // explicit with schema:
    'Order'   => ['source' => 'orders', 'schema' => 'public'],
    // with a connection role (read + write):
    'Log'     => ['source' => 'logs', 'connection' => 'logging'],
    // with separate read/write connections:
    'Stat'    => ['source' => 'stats', 'read' => 'replica', 'write' => 'primary'],
]);

// Register as the AppContext default so Query::new() picks it up
AppContext::instance()->set(TableResolver::class, new ChainResolver(
    new ModelResolver(),
    new MappingResolver($mapping),
));
```

Once registered, use the logical name wherever `Query` accepts a table or model reference:

```php
// Query::new() uses the AppContext default resolver (the chain above)
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

For a one-off mapping without registering globally, use `Query::using()`:

```php
use Azera\Db\Resolver\MappingResolver;

$results = Query::new()
    ->using(new MappingResolver($mapping))
    ->table('User')
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
```

### Fluent builder

Use the `add()` method to build mappings programmatically:

```php
$mapping = (new ModelMapping())
    ->add('User', 'users')
    ->add('Order', 'orders', 'public')           // third arg is the schema
    ->add('Log', 'logs', null, 'logging')         // fourth arg is connection (read+write)
    ->add('Stat', 'stats', null, null, 'replica', 'primary'); // read, write overrides
```

### Connection roles

Each mapping entry can specify connection roles:

- `connection` — sets both read and write to the same role
- `read` — overrides the read connection role
- `write` — overrides the write connection role

When `read`/`write` are set, they take precedence over `connection`.

> **Note:** `ModelMapping` only affects `Query`-level operations. The Active Record helpers (`User::find()`, `User::create()`, etc.) still require a PHP class that extends `Model`.

## Related

- [Database Queries](05-DATABASE-QUERIES.md)
- [Cookbook](10-COOKBOOK.md)
- [API Reference](api/README.md)
