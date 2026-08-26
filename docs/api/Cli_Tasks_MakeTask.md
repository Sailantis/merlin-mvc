# 🧩 Class: MakeTask

**Full name:** [Azera\Cli\Tasks\MakeTask](../../src/Cli/Tasks/MakeTask.php)

Scaffold boilerplate files in the correct PSR-4 directories.

Usage:
  make:controller <Name>     Create a controller class
  make:model <Name>          Create a model class
  make:task <Name>           Create a CLI task class
  make:middleware <Name>     Create a middleware class
  make:view <name>           Create a Clarity template file

Options:
  --force            Overwrite existing files
  --namespace=<ns>   Override the auto-detected root namespace

Examples:
  make:controller Foo
  make:model User --force
  make:task SyncPrices
  make:middleware Auth
  make:view home
  make:controller Foo --namespace=App\\Admin

## 🌍 Public Properties

- `public` [Console](Cli_Console.md) `$console` · [source](../../src/Cli/Tasks/MakeTask.php)
- `public` array `$options` · [source](../../src/Cli/Tasks/MakeTask.php)

## 🚀 Public methods

### controllerAction() · [source](../../src/Cli/Tasks/MakeTask.php#L36)

`public function controllerAction(string $name = ''): void`

Create a controller class.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | `''` | Controller name (e.g. "Foo" or "UserController") |

**➡️ Return value**

- Type: void


---

### modelAction() · [source](../../src/Cli/Tasks/MakeTask.php#L57)

`public function modelAction(string $name = ''): void`

Create a model class.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | `''` | Model name (e.g. "User" or "BlogPost") |

**➡️ Return value**

- Type: void


---

### taskAction() · [source](../../src/Cli/Tasks/MakeTask.php#L79)

`public function taskAction(string $name = ''): void`

Create a CLI task class.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | `''` | Task name (e.g. "SyncPrices" or "SendEmails") |

**➡️ Return value**

- Type: void


---

### middlewareAction() · [source](../../src/Cli/Tasks/MakeTask.php#L100)

`public function middlewareAction(string $name = ''): void`

Create a middleware class.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | `''` | Middleware name (e.g. "Auth" or "Cors") |

**➡️ Return value**

- Type: void


---

### viewAction() · [source](../../src/Cli/Tasks/MakeTask.php#L121)

`public function viewAction(string $name = ''): void`

Create a Clarity template view file.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$name` | string | `''` | View name (e.g. "home", "users.index") |

**➡️ Return value**

- Type: void



---

[Back to the Index ⤴](README.md)
