# 🧩 Class: ModelSyncTask

**Full name:** [Merlin\Cli\Tasks\ModelSyncTask](../../src/Cli/Tasks/ModelSyncTask.php)

CLI task for synchronising PHP model properties from the database schema (DB→PHP)
and for scaffolding new model files from database tables.

Usage:
  model-sync all   [<models-dir>] [--apply] [--database=<role>]
                                [--generate-accessors] [--no-deprecate]
                                [--field-visibility=<public|protected|private>]
                                [--create-missing] [--namespace=<ns>]
  model-sync model <file-or-class> [--apply] [--database=<role>]
                                [--generate-accessors]
                                [--field-visibility=<public|protected|private>]
                                [--no-deprecate] [--directory=<dir>]
  model-sync make  <ClassName>  [<directory>] [--apply]
                                [--database=<role>] [--namespace=<ns>]
                                [--generate-accessors] [--no-deprecate]
                                [--field-visibility=<public|protected|private>]

The <file-or-class> argument for `model-sync model` accepts:

  - A file path:               src/Models/User.php

  - A short class name:        User          (discovered via PSR-4 / --directory)

  - A fully-qualified name:    App\Models\User

By default the task only reports changes.
Pass --apply to write the updated model files to disk.

Options:
  --apply                     Apply changes to files instead of just
                              reporting them
  --create-missing            Create new model files for tables that don't
                              have a corresponding model yet
  --database=<role>           Database role to use for schema introspection
                              (default: "read")
  --directory=<dir>           Hint directory for class-name resolution in
                              `model-sync model` (optional)
  --field-visibility=<vis>    Visibility for generated properties (default:
                              "public")
  --generate-accessors        Also generate getter/setter methods for each
                              property
  --namespace=<ns>            Namespace to use when creating new model files
                              (required if --create-missing is used)
  --no-deprecate              Don't add @deprecated tags to removed
                              properties

Examples:
  php console.php model-sync all                                          # auto-discover App\Models
  php console.php model-sync all  src/Models                              # dry-run
  php console.php model-sync all  src/Models --apply                      # apply
  php console.php model-sync all  src/Models --apply --generate-accessors # with accessors
  php console.php model-sync all  src/Models --apply --field-visibility=protected
  php console.php model-sync all  src/Models --apply --no-deprecate
  php console.php model-sync all  src/Models --apply --create-missing --namespace=App\\Models
  php console.php model-sync model src/Models/User.php --apply            # file path
  php console.php model-sync model User --apply                           # short class name (PSR-4)
  php console.php model-sync model App\\Models\\User --apply              # fully-qualified name
  php console.php model-sync model User --directory=src/Models --apply    # with directory hint
  php console.php model-sync make  User                                   # auto-discover App\Models dir
  php console.php model-sync make  User src/Models --namespace=App\\Models --apply

## 🌍 Public Properties

- `public` [Console](Cli_Console.md) `$console` · [source](../../src/Cli/Tasks/ModelSyncTask.php)
- `public` array `$options` · [source](../../src/Cli/Tasks/ModelSyncTask.php)

## 🚀 Public methods

### allAction() · [source](../../src/Cli/Tasks/ModelSyncTask.php#L85)

`public function allAction(string $dir = ''): void`

Scan a directory recursively, find all PHP files that extend Model,
and sync each one against the database.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$dir` | string | `''` | Directory to scan (optional – defaults to App\\Models via PSR-4) |

**➡️ Return value**

- Type: void


---

### modelAction() · [source](../../src/Cli/Tasks/ModelSyncTask.php#L178)

`public function modelAction(string $file = ''): void`

Sync a single model against the database.

The argument may be a file path, a short class name, or a fully-qualified
class name. Short and qualified class names are resolved to file paths
via the PSR-4 autoloading map. Use --directory=<dir> to narrow the search
when two classes share the same short name.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$file` | string | `''` | File path, short class name, or fully-qualified class name (required) |

**➡️ Return value**

- Type: void


---

### makeAction() · [source](../../src/Cli/Tasks/ModelSyncTask.php#L224)

`public function makeAction(string $className = '', string $dir = ''): void`

Scaffold a new model class from a database table and immediately sync its properties.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$className` | string | `''` | Short class name without namespace (e.g. User) |
| `$dir` | string | `''` | Target directory for the new file (optional – defaults to App\\Models via PSR-4) |

**➡️ Return value**

- Type: void



---

[Back to the Index ⤴](README.md)
