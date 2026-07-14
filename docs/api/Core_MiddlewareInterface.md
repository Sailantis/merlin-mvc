# 🔌 Interface: MiddlewareInterface

**Full name:** [Merlin\Core\MiddlewareInterface](../../src/Core/MiddlewareInterface.php)

Contract for all middleware classes in the Merlin pipeline.

Implementations receive the application context and a callable representing
the remainder of the pipeline. They can short-circuit processing by returning
a [`Response`](Http_Response.md) directly, or continue by calling `$next()` and
optionally modifying its result.

## 🚀 Public methods

### process() · [source](../../src/Core/MiddlewareInterface.php#L25)

`public function process(Merlin\AppContext $context, callable $next): Merlin\Http\Response|null`

Process the incoming request and optionally delegate to the next handler.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$context` | [AppContext](AppContext.md) | - | Application context for the current request. |
| `$next` | callable | - | Callable that invokes the remaining pipeline. Returns ?Response. |

**➡️ Return value**

- Type: [Response](Http_Response.md)|null
- Description: Response to send, or null to continue (caller resumes the pipeline).



---

[Back to the Index ⤴](README.md)
