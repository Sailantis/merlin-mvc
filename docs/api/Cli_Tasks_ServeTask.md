# 🧩 Class: ServeTask

**Full name:** [Azera\Cli\Tasks\ServeTask](../../src/Cli/Tasks/ServeTask.php)

Start the PHP built-in development server.

Usage:
  serve [--host=<addr>] [--port=<n>] [--docroot=<dir>]

Options:
  --host=<addr>     Host address to bind to (default: 0.0.0.0)
  --port=<n>        Port number to listen on (default: 8000)
  --docroot=<dir>   Document root directory relative to project root
                    (default: public)

Examples:
  serve                       # start on 0.0.0.0:8000
  serve --port=8888           # start on port 8888
  serve --host=127.0.0.1      # bind to localhost only

## 🌍 Public Properties

- `public` [Console](Cli_Console.md) `$console` · [source](../../src/Cli/Tasks/ServeTask.php)
- `public` array `$options` · [source](../../src/Cli/Tasks/ServeTask.php)

## 🚀 Public methods

### runAction() · [source](../../src/Cli/Tasks/ServeTask.php#L26)

`public function runAction(): void`

**➡️ Return value**

- Type: void



---

[Back to the Index ⤴](README.md)
