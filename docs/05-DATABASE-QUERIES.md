# Database Queries

**Master the query builder** - Deep dive into Merlin's powerful and intuitive query builder. Learn how to construct complex SELECT queries, perform joins, use subqueries, aggregate data, and leverage prepared statements for security.

Merlin uses a unified fluent query builder: `Merlin\Db\Query`.
You can access it directly via `Query::new()` or through models with `Model::query()`.

## Basic Setup

Before running queries, configure database connection(s) in your application context. This makes the database available throughout your application.

```php
use Merlin\AppContext;
use Merlin\Db\Database;

AppContext::instance()->dbManager()->set('default', new Database(
    'mysql:host=localhost;dbname=myapp',
    'user',
    'pass'
));
```

## Query Entry Points

You can build queries in two ways: directly using Query::new() for table-level operations, or through models for object-oriented workflows.

```php
use Merlin\Db\Query;

// Plain table
$q = Query::new()->table('users');

// From model
$users = User::query()->where('status', 'active')->select();
```

## SELECT

The query builder provides a fluent interface for constructing SELECT queries. Chain methods to add conditions, joins, sorting, and pagination. All queries use prepared statements for security.

```php
$users = Query::new()
    ->table('users', 'u')
    ->columns(['u.id', 'u.username', 'u.email'])
    ->where('u.created_at >', '2024-01-01')
    ->where('u.status', 'active')
    ->orderBy('u.created_at DESC')
    ->limit(20)
    ->offset(0)
    ->select();

$user = Query::new()
    ->table('users')
    ->where('id', 5)
    ->first();

// DISTINCT
$emails = Query::new()
    ->table('orders')
    ->columns(['customer_email'])
    ->distinct(true)
    ->select();
```

## WHERE Styles

Merlin supports three where clause styles to accommodate different preferences. All are equally safe and use prepared statements behind the scenes.

```php
// Condition + inline values (values are escaped and inserted into SQL)
User::query()->where('email = :email', ['email' => 'a@example.com'])->first();

// Condition + bound parameters (values remain real PDO parameters)
User::query()->where('email = :email')->bind(['email' => 'a@example.com'])->first();

// Column/value pair – shorthand for WHERE email = 'a@example.com'
User::query()->where('email', 'a@example.com')->first();

// Column/value pair with explicit operator
User::query()->where('email <>', 'a@example.com')->first();

// OR condition
User::query()
    ->where('status', 'active')
    ->orWhere('status', 'pending')
    ->select();
```

## Condition Grouping

Use `groupStart()` / `groupEnd()` to wrap parts of a WHERE clause in parentheses. The `orGroupStart()`, `notGroupStart()`, and `orNotGroupStart()` variants prefix the group with `OR`, `NOT`, or `OR NOT` respectively.

```php
// WHERE (status = 'active') AND (role = 'admin' OR role = 'moderator')
User::query()
    ->where('status', 'active')
    ->groupStart()
        ->where('role', 'admin')
        ->orWhere('role', 'moderator')
    ->groupEnd()
    ->select();

// WHERE (status = 'active') AND NOT (banned = 1)
User::query()
    ->where('status', 'active')
    ->notGroupStart()
        ->where('banned', 1)
    ->groupEnd()
    ->select();
```

## IN / NOT IN

`inWhere()` and `notInWhere()` generate `IN (…)` / `NOT IN (…)` clauses. They also accept another `Query` instance to produce a subquery.

```php
// Simple value list
User::query()->inWhere('status', ['active', 'pending'])->select();
User::query()->notInWhere('role', ['guest', 'banned'])->select();

// OR variant
User::query()
    ->where('department', 'sales')
    ->orInWhere('role', ['admin', 'manager'])
    ->select();

// Subquery as value source
$activeIds = User::query()->columns('id')->where('status', 'active');
Post::query()->inWhere('user_id', $activeIds)->select();
```

## BETWEEN

```php
// WHERE created_at BETWEEN '2025-01-01' AND '2025-12-31'
User::query()->betweenWhere('created_at', '2025-01-01', '2025-12-31')->select();

// WHERE NOT (age BETWEEN 18 AND 65)
User::query()->notBetweenWhere('age', 18, 65)->select();

// OR variant
User::query()
    ->where('status', 'active')
    ->orBetweenWhere('score', 90, 100)
    ->select();
```

## LIKE

```php
// WHERE username LIKE 'john%'
User::query()->likeWhere('username', 'john%')->select();

// WHERE username NOT LIKE '%bot%'
User::query()->notLikeWhere('username', '%bot%')->select();

// OR LIKE / OR NOT LIKE
User::query()
    ->where('status', 'active')
    ->orLikeWhere('email', '%@example.com')
    ->select();
```

## FROM Subquery

Use a `Query` instance as the table source for a derived table. The subquery is wrapped in parentheses and its bind parameters are automatically merged into the parent query.

```php
use Merlin\Db\Query;

// Build the inner query independently
$recent = Query::new()
    ->table('orders')
    ->where('created_at > :since', ['since' => '2025-01-01'])
    ->columns(['user_id', 'total']);

// Use it as a derived table with an alias
$results = Query::new()
    ->from($recent, 'recent_orders')
    ->where('recent_orders.total >', 100)
    ->select();
// Produces: SELECT * FROM (SELECT `user_id`, `total` FROM `orders` WHERE ...) AS `recent_orders` WHERE ...
```

`from()` also accepts a plain table name string (same as `table()`):

```php
// Equivalent: plain string still works
$q = Query::new()->from('users', 'u')->where('u.status', 'active')->select();
```

## JOIN, GROUP, HAVING

Build complex queries with joins, aggregations, and grouping. The query builder makes it easy to construct sophisticated SQL while maintaining readability.

```php
$rows = Query::new()
    ->table('posts', 'p')
    ->columns([
        'p.id',
        'p.title',
        'u.username',
        'COUNT(c.id) AS comments_count',
    ])
    ->join('users', 'u', 'u.id = p.user_id')
    ->leftJoin('comments', 'c', 'c.post_id = p.id')
    ->where('p.status', 'published')
    ->groupBy('p.id')
    ->having('COUNT(c.id) > :min', ['min' => 0])
    ->orderBy('comments_count DESC')
    ->select();
```

### Subquery in JOIN

Any join method (`join`, `innerJoin`, `leftJoin`, `rightJoin`, `crossJoin`) also accepts a `Query` instance as the first argument. Supply an alias as the second argument so the outer query can reference it.

```php
// Pre-aggregate orders into a subquery
$orderTotals = Query::new()
    ->table('orders')
    ->where('status', 'completed')
    ->groupBy('user_id')
    ->columns(['user_id', 'SUM(total) AS total_spent']);

$results = Query::new()
    ->table('users', 'u')
    ->leftJoin($orderTotals, 'ot', 'ot.user_id = u.id')
    ->columns(['u.username', 'ot.total_spent'])
    ->where('ot.total_spent >', 500)
    ->select();
// Produces: SELECT ... FROM `users` AS `u`
//   LEFT JOIN (SELECT `user_id`, SUM(total) AS total_spent
//              FROM `orders` WHERE ... GROUP BY `user_id`) AS `ot` ON (ot.user_id = u.id)
//   WHERE ...
```

Bind parameters from the subquery are automatically propagated to the parent query — you never need to merge them manually.

## INSERT / UPSERT / UPDATE / DELETE

Beyond SELECT queries, the query builder handles all write operations. INSERT returns the new ID, UPDATE and DELETE return affected row counts for verification.

```php
// INSERT
$id = User::query()->insert([
    'username' => 'john',
    'email' => 'john@example.com',
]);

// INSERT with bound parameters
$id = User::query()->bind([
    'username' => 'john',
    'email' => 'john@example.com',
])->insert();

// UPSERT
User::query()->upsert([
    'id' => 1,
    'username' => 'john',
    'email' => 'john@example.com',
]);

// UPSERT with bound parameters
User::query()->bind([
    'id' => 1,
    'username' => 'john',
    'email' => 'john@example.com',
])->upsert();

// UPDATE
$affected = User::query()
    ->where('id', 1)
    ->update(['email' => 'john.new@example.com']);

// UPDATE with bound parameters
$affected = User::query()
    ->where('id', 1)
    ->bind(['email' => 'john.new@example.com'])
    ->update();

// DELETE
$deleted = User::query()
    ->where('status', 'inactive')
    ->delete();

// DELETE with bound parameters
$deleted = User::query()
    ->where('status = :status')
    ->bind(['status' => 'inactive'])
    ->delete();
```

### Bulk INSERT

Insert multiple rows in a single statement with `bulkValues()`:

```php
Query::new()->table('tags')->bulkValues([
    ['name' => 'php'],
    ['name' => 'mysql'],
    ['name' => 'redis'],
])->insert();
```

### INSERT IGNORE / REPLACE INTO

`ignore()` silently skips duplicate-key violations. `replace()` uses `REPLACE INTO` (MySQL/SQLite), which deletes the conflicting row and re-inserts.

```php
// INSERT IGNORE INTO (MySQL/SQLite) / ON CONFLICT DO NOTHING (PostgreSQL)
User::query()->ignore()->insert(['email' => 'existing@example.com', 'username' => 'john']);

// REPLACE INTO (MySQL/SQLite only)
User::query()->replace()->insert(['id' => 1, 'email' => 'updated@example.com']);
```

### RETURNING clause (PostgreSQL / MySQL 8.0.27+)

Chain `returning()` on INSERT, UPDATE, or DELETE to get column values back from the database:

```php
// Insert and retrieve the generated id and created_at in one round-trip
$row = User::query()
    ->returning(['id', 'created_at'])
    ->insert(['username' => 'alice', 'email' => 'alice@example.com']);

// Update and get the new value back
$row = User::query()
    ->where('id', 5)
    ->returning('updated_at')
    ->update(['status' => 'active']);
```

### TRUNCATE

```php
Query::new()->table('cache_entries')->truncate();
```

## EXISTS / COUNT

```php
// Simple where with inline value
$exists = User::query()->where('email', 'john@example.com')->exists();
$total = User::query()->where('status', 'active')->count();

// With bound parameters
$exists = User::query()->where('email = :email')->bind(['email' => 'john@example.com'])->exists();
$total = User::query()->where('status = :status')->bind(['status' => 'active'])->count();
```

> **Note:** The query builder terminal method is `count()`. The static model helper `Model::count()` is a thin wrapper around it.

```php
// Model-level count
$active = User::count(['status' => 'active']);
```

## Locking

Use `forUpdate()` for pessimistic write locks and `sharedLock()` for shared/read locks. Both are supported on MySQL and PostgreSQL.

```php
// SELECT … FOR UPDATE (exclusive lock)
$user = Query::new()
    ->table('users')
    ->where('id', 5)
    ->forUpdate(true)
    ->first();

// SELECT … LOCK IN SHARE MODE (MySQL) / FOR SHARE (PostgreSQL)
$user = Query::new()
    ->table('users')
    ->where('id', 5)
    ->sharedLock(true)
    ->first();
```

## Pagination with Paginator

Use `Merlin\Db\Paginator` to paginate any query builder. The paginator runs a count() query first, then fetches the requested page using `LIMIT/OFFSET`.

```php
$paginator = User::query()
    ->where('status', 'active')
    ->orderBy('created_at DESC')
    ->paginate(page: 2, pageSize: 20);

$paginator->execute();

$meta = [
    'currentPage' => $paginator->getCurrentPage(),
    'lastPage' => $paginator->getLastPage(),
    'totalItems' => $paginator->getTotalItems(),
    'firstItem' => $paginator->getFirstItem(),
    'lastItem' => $paginator->getLastItem(),
    'pageSize' => $paginator->getPageSize(),
];

$items = $paginator->get(); // array of User models for page 2
```

You can enable reverse pagination using the third argument. It does not change your original ORDER BY. It only flips how pages are calculated, so page 1 returns the last items instead of the first ones.

```php
// Messages sorted oldest → newest
$messages = Query::new()
    ->table('messages')
    ->where('room_id', 15)
    ->orderBy('id ASC')
    ->paginate(page: 1, pageSize: 3, reverse: true)
    ->execute();
// Returns the LAST 3 messages, not the first 3.
```

## Sql Expressions

`Merlin\Db\Sql` is a typed value-object system for embedding SQL expressions inside query builder calls. All helpers are static factory methods; the resulting node is serialized to safe SQL at query-compile time.

### Sql::raw() — literal SQL fragments

Inject unescaped SQL. Optional `$inlineValues` replaces named placeholders with escaped literals before the fragment is inserted.

```php
use Merlin\Db\Sql;

// Increment counter in-place
Post::query()
    ->where('id', 5)
    ->update(['view_count' => Sql::raw('view_count + 1')]);

// Named placeholders inlined as escaped literals
Post::query()
    ->where('user_id', 1)
    ->update(['status' => Sql::raw(
        'CASE WHEN view_count > :popular THEN :pub ELSE :draft END',
        ['popular' => 100, 'pub' => 'published', 'draft' => 'draft']
    )]);
```

### Sql::param() / Sql::bind() — PDO bound parameters

`Sql::param(name)` emits `:name` as a placeholder; the value must arrive via `Query::bind()`.  
`Sql::bind(name, value)` emits `:name` **and** carries the value as a real PDO parameter — useful for binary data, full-text vectors, or JSON blobs that should not be inlined.

```php
// param() — value supplied separately via bind()
Query::new()->table('articles')
    ->bind(['userId' => 42, 'ts' => time()])
    ->set([
        'updated_by' => Sql::param('userId'),
        'updated_at' => Sql::func('FROM_UNIXTIME', [Sql::param('ts')]),
    ])
    ->where('id', 1)
    ->update();

// bind() — value travels with the node
User::query()
    ->where('id', Sql::bind('uid', 42))
    ->update(['score' => Sql::raw('score + :inc', ['inc' => 5])]);
```

| Helper                           | SQL output        | Value delivery                                                |
| -------------------------------- | ----------------- | ------------------------------------------------------------- |
| `Sql::raw('x + :n', ['n' => 1])` | `x + 1` (literal) | Inlined (escaped)                                             |
| `Sql::param('n')`                | `:n`              | Placeholder only — value must be supplied via `Query::bind()` |
| `Sql::bind('n', 1)`              | `:n`              | Placeholder **and** value bubbled as PDO param                |

### Sql::column() — unquoted identifier reference

Refer to a column by name without quoting it as a string value.

```php
// Use column reference inside a function argument
Post::query()
    ->set('search', Sql::cast(
        Sql::func('to_tsvector', ['simple', Sql::column('title')]),
        'tsvector'
    ))
    ->where('id', 1)
    ->update();
// PostgreSQL: UPDATE posts SET search = to_tsvector('simple', title)::tsvector WHERE (id = 1)
```

### Sql::func() — SQL function calls

```php
// NOW(), COALESCE(), custom functions, …
Query::new()->table('sessions')
    ->insert([
        'user_id'    => 42,
        'created_at' => Sql::func('NOW'),
        'expires_at' => Sql::func('DATE_ADD', [Sql::func('NOW'), Sql::raw('INTERVAL 30 MINUTE')]),
    ]);
```

### Sql::cast() — type casting

Driver-aware: PostgreSQL uses `expr::type`, MySQL/SQLite use `CAST(expr AS type)`.

```php
Query::new()->table('stats')
    ->columns([Sql::cast(Sql::column('score'), 'DECIMAL(10,2)')->as('score_decimal')])
    ->select();
```

### Sql::concat() — string concatenation

Driver-aware: MySQL uses `CONCAT()`, PostgreSQL/SQLite use `||`.

```php
User::query()
    ->set('display_name', Sql::concat(Sql::column('first_name'), ' ', Sql::column('last_name')))
    ->where('id', 1)
    ->update();
```

### Sql::json() — JSON values

Serializes a PHP array/value to a JSON string literal.

```php
User::query()
    ->set('preferences', Sql::json(['theme' => 'dark', 'lang' => 'en']))
    ->where('id', 1)
    ->update();
```

### Sql::pgArray() — PostgreSQL array literals

```php
Query::new()->table('posts')
    ->insert([
        'title' => 'PHP Tutorial',
        'tags'  => Sql::pgArray(['php', 'programming', 'web']),
    ]);
// INSERT INTO posts (title, tags) VALUES ('PHP Tutorial', '{"php","programming","web"}')
```

### Sql::csList() — comma-separated list (IN clauses)

```php
User::query()
    ->where('id IN (' . Sql::csList([1, 2, 3]) . ')')
    ->select();
```

### Sql::expr() — composite expressions

Concatenates parts with spaces. Plain strings are treated as raw SQL tokens; wrap values in `Sql::value()` to escape them.

```php
$expr = Sql::expr('COALESCE(', Sql::column('score'), ',', Sql::value(0), ')');
User::query()->columns([$expr->as('score')])->select();
```

### Sql::case() — CASE expressions

```php
$status = Sql::case()
    ->when(Sql::raw('score >= 90'), 'excellent')
    ->when(Sql::raw('score >= 70'), 'good')
    ->else('average')
    ->end();

User::query()
    ->columns(['id', 'username', $status->as('rating')])
    ->select();
```

### Sql::subQuery() — scalar subquery in column list

```php
$lastLogin = Sql::subQuery(
    Query::new()->table('logins')
        ->columns('MAX(created_at)')
        ->where('user_id = users.id')
);

Query::new()
    ->table('users')
    ->columns(['id', 'username', $lastLogin->as('last_login')])
    ->select();
```

### ->as() — alias any Sql node

Any `Sql` node can be aliased with `->as('alias')`, useful in column lists:

```php
->columns([
    Sql::func('COUNT', ['*'])->as('total'),
    Sql::raw('SUM(amount)')->as('revenue'),
])
```

## Returning SQL Without Executing

```php
$sql = User::query()
    ->where('status', 'active')
    ->returnSql()
    ->select();
```

## Transactions

Use `Merlin\Db\Database` transaction methods:

```php
$db = AppContext::instance()->dbManager()->get('write');

$db->begin();
try {
    User::query()->insert(['username' => 'alice', 'email' => 'alice@example.com']);
    User::query()->where('id', 1)->update(['status' => 'active']);
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}
```

## See Also

- [Models & ORM](04-MODELS-ORM.md)
- [Cookbook](10-COOKBOOK.md)
- [API Reference](api/index.md)
