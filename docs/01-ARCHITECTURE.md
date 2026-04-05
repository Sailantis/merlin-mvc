# Architecture

**Understanding Merlin's design** - Learn how Merlin's core components fit together, from the AppContext service container to the MVC layer, database abstraction, and CLI tools. This guide explains the framework's architectural principles and design decisions.

This document outlines how core Merlin components work together.

![Merlin Overall Architecture](images/architecture-overview.svg)

## Core Principles

- Lightweight runtime with minimal mandatory dependencies
- Explicit routing and dispatch
- Unified query API for model and table workflows
- Read/write DB separation support
- Simple composition through `AppContext`

## Main Components

Merlin is organized into distinct layers, each handling a specific concern. Understanding these components helps you leverage the framework effectively.

### `AppContext`

Central runtime context and service container – accessed as a singleton via `AppContext::instance()`.

Built-in lazy service accessors:

| Method        | Returns                                                |
| ------------- | ------------------------------------------------------ |
| `request()`   | `Merlin\Http\Request`                                  |
| `view()`      | `Merlin\Mvc\ViewEngine`                                |
| `session()`   | `Merlin\Http\Session\|null`                            |
| `cookies()`   | `Merlin\Http\Cookies`                                  |
| `dbManager()` | `Merlin\Db\DatabaseManager`                            |
| `route()`     | `Merlin\ResolvedRoute\|null` – populated by Dispatcher |

Custom services can be registered with `$ctx->set($id, new MyService())` or `$ctx->set($id, fn() => new MyService())` and retrieved with `$ctx->get($id)`. Registered callables are treated as zero-argument lazy factories and cached on first resolution. Auto-wiring is supported: unregistered class names are instantiated via reflection with their constructor dependencies resolved recursively from the container.

### MVC Layer

- `Router` matches URI + method to route patterns, extracting typed parameters; supports named routes, inline or scoped groups (`prefix()`, `namespace()`, `controller()`, `middleware()`), and custom parameter validators
- `Dispatcher` is instantiated without arguments; obtains `AppContext` internally. It resolves controllers via DI (`AppContext::get()`), runs the global and per-route middleware pipeline, injects action parameters (route vars or DI), and stores resolved route info via `AppContext::setRoute()`
- `Controller` provides access to request/context plus `beforeAction`/`afterAction` lifecycle hooks and controller-level middleware declarations
- `ViewEngine` renders templates, layouts, and namespaced views; supports global view variables and partial rendering
- `ModelMapping` maps logical model names to PHP classes and table names – useful when decoupling route/query naming from class names
- `MiddlewareInterface` defines the contract for all middleware; `SessionMiddleware` is the built-in implementation
- `ResolvedRoute` (in `AppContext->route()`) contains resolved route information accessible anywhere

### Data Layer

- `Model` provides Active Record style methods (`find`, `findAll`, `create`, `save`, `delete`, …) and state tracking (`saveState`/`hasChanged`)
- `Query` is the fluent SQL builder for select, write, count, and exists operations; terminal calls (`insert`, `upsert`, `update`, `delete`) finalize the query
- `Database` wraps PDO with transaction helpers and lazy connection creation
- `ResultSet` provides iterable, countable access to model or raw-row results
- `Paginator` wraps a `Query` builder to add page/offset/total metadata with minimal boilerplate
- `Condition`, `Sql`, and `SqlCase` provide composable SQL fragments and safe escape hatches (`Sql::bind()` for PDO-bound values)

### CLI Layer

- `Console` maps CLI arguments to `Task` classes and `*Action()` methods; additional namespaces can be registered for discovery
- `Task` is the base class for all commands
- `ModelSyncTask` is the built-in task that drives model synchronisation (see Sync Layer below)

## Request Flow (Web)

Understanding the request lifecycle helps you know where to hook in custom logic. Each request follows a clear path from router to controller to response.

```text
HTTP Request
  -> Router::match()
  -> Dispatcher::dispatch(routeInfo)
  -> Controller action
  -> Response
```

`Dispatcher` maps controller return types automatically:

- `Response` -> sent as-is
- `array` / `JsonSerializable` -> JSON response
- `string` -> text response
- `int` -> status response
- `null` -> `204`

## Data Flow

Merlin offers flexibility in how you interact with the database. Choose the approach that fits your needs - models for object-oriented work, or Query for direct table access.

Two common entry points:

```php
// Model-centric
$user = User::find(10);
$rows = User::query()->where('status', 'active')->select();

// Table-centric
$rows = Merlin\Db\Query::new()
    ->table('users')
    ->where('status', 'active')
    ->select();
```

Write operations are terminal builder calls (`insert`, `upsert`, `update`, `delete`).

## Read/Write Separation

Merlin supports replica setups through `DatabaseManager` roles.

Register named connections in bootstrap:

```php
$mgr = $ctx->dbManager();
$mgr->set('write', new Database('mysql:host=primary;dbname=app', 'rw', 'secret'));
$mgr->set('read',  fn() => new Database('mysql:host=replica;dbname=app', 'ro', 'secret'));
```

Models read from the `read` role and write to the `write` role by default, falling back to the registered default when the role is absent. Per-model overrides:

```php
User::setDefaultReadRole('replica');
User::setDefaultWriteRole('primary');
User::setDefaultRole('analytics'); // both read + write
```

For single-database apps, register one connection under any name – models fall through to the default automatically:

```php
$ctx->dbManager()->set('default', new Database(...));
```

## Sync Layer

Merlin ships a code-generation and schema-synchronisation subsystem under `Merlin\Sync`. It introspects a live database and updates (or generates) Model PHP files to match the schema, without requiring a separate migration runner.

Key components:

- `SyncRunner` – orchestrates the sync: reads schema, diffs against the parsed model, applies or previews changes
- `ModelParser` – parses an existing PHP model file into a `ParsedModel` with typed properties and accessors
- `ModelDiff` – computes the diff between the live schema and the parsed model (add/remove/change columns and accessors)
- `CodeGenerator` – writes the updated PHP source back to the model file
- `SyncOptions` / `SyncResult` – configure dry-run/preview mode and carry the result back to the caller
- `Schema\` – driver-specific providers (`MySqlSchemaProvider`, `PostgresSchemaProvider`, `SqliteSchemaProvider`) that return a normalised `TableSchema`

The CLI entry point is the built-in `ModelSyncTask`; the same `SyncRunner` API can be called programmatically.

## Extensibility Points

- Router custom parameter validators via `addType()`
- Router route groups via inline or scoped `prefix()`, `namespace()`, `controller()`, and `middleware()`
- Dispatcher middleware groups and controller factory
- Model overrides: `source()`, `schema()`, `idFields()`, `readConnection()`, `writeConnection()`
- Query SQL escape hatches via `Sql` nodes (`Sql::bind()` for PDO-bound params)
