# ModelSyncExample – Model Synchronisation Demo

This example demonstrates the **DB → PHP** model synchronisation feature of the Azera framework.  
You set up a database, run the sync task, and the framework automatically populates your model classes with typed PHP properties derived from the live database schema.

---

## Directory Structure

```
ModelSyncExample/
├── console.php          CLI entry point
├── bootstrap.php        Database connection setup
├── app/Models/
│   ├── User.php         Empty model shell → sync populates properties
│   ├── Post.php
│   └── Comment.php
└── sql/
    ├── sqlite.sql       SQLite table definitions (no server required)
    ├── mysql.sql        MySQL  table definitions
    └── postgresql.sql   PostgreSQL table definitions
```
---

## Quick Start (SQLite – no server required)

### 1. Create the database

```bash
cd examples/ModelSyncExample

# Create the SQLite database from the SQL file
sqlite3 sync_example.sqlite < sql/sqlite.sql
```

### 2. Configure the connection

The default `bootstrap.php` is already configured for SQLite.  
No changes needed for the quick start.

### 3. Preview changes (dry-run)

```bash
php console.php model sync-all app/Models
```

Expected output:

```
Dry-run: scanning 3 file(s) in app/Models …

[OK]    App\Models\Comment: no changes
[OK]    App\Models\Post: no changes
[DRY-RUN] App\Models\User (users): +1 added, -1 deprecated
    • add    $gravatar_hash: ?string
    • deprecate $avatar_url

Done. 1 model(s) with changes, 0 error(s).
```

> In dry-run mode **no files are modified**. This is the default and safe to run any time.

### 4. Apply changes

```bash
php console.php model sync-all app/Models --apply
```

The model files in `app/Models/` are now updated with the correct typed properties.  
Open `app/Models/User.php` to see the result — it will look similar to:

```php
class User extends Model
{
    public int $id;
    public string $email;
    public ?string $name;
    public string $status;
    public string $created_at;
    public ?string $updated_at;
    public ?string $gravatar_hash;
}
```

### 5. Sync a single model

You can identify a model by file path, short class name, or fully-qualified class name:

```bash
# By file path (original behaviour)
php console.php model sync app/Models/User.php --apply

# By short class name – discovered automatically via PSR-4
php console.php model sync User --apply

# By fully-qualified class name
php console.php model sync App\Models\User --apply
```

When a class name is given instead of a file path, the task resolves it to the
correct file using the PSR-4 map in `composer.json`. Add `--directory=<dir>` if
you have multiple classes with the same short name in different namespaces.

---

## Using MySQL

### 1. Create the database

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS sync_example;"
mysql -u root -p sync_example < sql/mysql.sql
```

### 2. Configure the connection

Edit `bootstrap.php` — comment out the SQLite section and uncomment the MySQL section:

```php
$ctx->dbManager()->set('default', new Database(
    dsn:  'mysql:host=127.0.0.1;dbname=sync_example;charset=utf8mb4',
    user: 'root',
    pass: 'secret'
));
```

### 3. Run sync

```bash
php console.php model sync-all app/Models --apply
```

> **MySQL advantage:** Column and table comments from the SQL file are synced into  
> PHP docblocks automatically.

---

## Using PostgreSQL

### 1. Create the database

```bash
psql -U postgres -c "CREATE DATABASE sync_example;"
psql -U postgres -d sync_example -f sql/postgresql.sql
```

### 2. Configure the connection

Edit `bootstrap.php` — uncomment the PostgreSQL section:

```php
$ctx->dbManager()->set('default', new Database(
    dsn:  'pgsql:host=127.0.0.1;dbname=sync_example',
    user: 'postgres',
    pass: 'secret'
));
```

### 3. Run sync

```bash
php console.php model sync-all app/Models --apply
```

---

## Testing Incremental Sync

### Add a column (simulate schema evolution)

```sql
-- SQLite (run inside sqlite3 sync_example.sqlite):
ALTER TABLE users ADD COLUMN avatar_url TEXT;

-- MySQL:
ALTER TABLE `users` ADD COLUMN `avatar_url` VARCHAR(500) NULL COMMENT 'Profile picture URL';

-- PostgreSQL:
ALTER TABLE users ADD COLUMN avatar_url TEXT;
COMMENT ON COLUMN users.avatar_url IS 'Profile picture URL';
```

The example `app/Models/User.php` ships WITHOUT `$avatar_url` — run the sync
above once to bring it in sync with the base schema first (it will add
`$gravatar_hash` and deprecate the stale `$avatar_url` shell property).
Then this ALTER adds the column back and sync appends `$avatar_url`.

Re-run:

```bash
# By file path
php console.php model sync app/Models/User.php --apply

# Or by class name
php console.php model sync User --apply
```

`$avatar_url` (and the column comment as a docblock for MySQL/PG) is appended to `User.php`.

### Remove a column (simulate column removal)

Re-run sync:

```bash
php console.php model sync-all app/Models --apply
```

Any PHP property that no longer has a matching column is marked `@deprecated` in its docblock rather than deleted — keeping your code safe.

---

## CLI Reference

The task name is `model`; the sync actions are `list`, `sync-all`, `sync` and `new`.

```
php console.php model sync-all  <directory>       [--apply] [--database=<role>]
                            [--generate-accessors] [--field-visibility=<vis>]
                            [--no-deprecate] [--create-missing] [--namespace=<ns>]
php console.php model sync      <file-or-class>   [--apply] [--database=<role>]
                            [--generate-accessors] [--field-visibility=<vis>]
                            [--no-deprecate] [--directory=<dir>]
php console.php model new       <ClassName> [<dir>] [--namespace=<ns>] [--apply]
                            [--database=<role>] [--generate-accessors]
                            [--field-visibility=<vis>] [--no-deprecate]
php console.php model list      [<models-dir>] [--database=<role>] [--missing]
```

The `<file-or-class>` argument for `model sync` accepts:

| Form                 | Example                            |
| -------------------- | ---------------------------------- |
| File path            | `app/Models/User.php`              |
| Short class name     | `User` (auto-discovered via PSR-4) |
| Fully-qualified name | `App\Models\User`                  |

| Flag                       | Description                                                                              |
| -------------------------- | ---------------------------------------------------------------------------------------- |
| _(none)_                   | Dry-run mode: preview changes without modifying files                                    |
| `--apply`                  | Apply changes and write updated model files to disk                                      |
| `--database=<role>`        | Use a specific database role (default: `read`)                                           |
| `--generate-accessors`     | Generate a camelized getter/setter method for each new property                          |
| `--field-visibility=<vis>` | Property visibility: `public` (default), `protected`, or `private`                       |
| `--no-deprecate`           | Skip `@deprecated` tags on properties whose columns have been removed                    |
| `--create-missing`         | (`sync-all` only) Scaffold model files for tables that have no matching model yet        |
| `--directory=<dir>`        | (`model sync` only) Directory hint when resolving a short class name                     |
| `--namespace=<ns>`         | PHP namespace to use when scaffolding new model files (required with `--create-missing`) |

---

## How it works

1. **ModelParser** tokenises the PHP file and extracts class properties + metadata
2. **SchemaProvider** (MySQL / PostgreSQL / SQLite) introspects the live database schema for the model's table
3. **ModelDiff** computes the difference between PHP properties and DB columns:
   - Missing PHP property → `AddProperty` operation (respects `--field-visibility`)
   - Missing DB column → `RemoveProperty` (marks existing property `@deprecated`, unless `--no-deprecate`)
   - Type mismatch → `UpdatePropertyType`
   - Comment mismatch → `UpdatePropertyComment`
   - With `--generate-accessors`: an `AddAccessor` is paired with each added property, producing a camelized dual-purpose getter/setter
   - Column names starting with `_` and properties starting with `_` are always skipped
4. **CodeGenerator** applies the operations to the PHP source file using string manipulation and regex
5. **SyncRunner** orchestrates the pipeline and writes the result back to disk
