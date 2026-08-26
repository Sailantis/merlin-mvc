# 🔌 Interface: GuardInterface

**Full name:** [Azera\Security\GuardInterface](../../src/Security/GuardInterface.php)

Contract for an authentication guard.

A guard is responsible for a single authentication strategy: validating
credentials, persisting the authenticated state (session, token, etc.),
and exposing the authenticated user.

Implementations live in the companion package `azera/auth` (session
guard, token guard, JWT guard). The framework ships the contract so
application code can depend on the interface.

## 🚀 Public methods

### attempt() · [source](../../src/Security/GuardInterface.php#L30)

`public function attempt(array $credentials): bool`

Attempt to authenticate with the given credentials.

On success the authenticated state MUST be persisted so that
subsequent calls to `check()` return true.

**🧭 Parameters**

| Name | Type | Default | Description |
|---|---|---|---|
| `$credentials` | array | - | Credential bag (e.g.<br>`['email' => …, 'password' => …]`). |

**➡️ Return value**

- Type: bool
- Description: True if authentication succeeded.


---

### check() · [source](../../src/Security/GuardInterface.php#L35)

`public function check(): bool`

Check whether a user is currently authenticated.

**➡️ Return value**

- Type: bool


---

### user() · [source](../../src/Security/GuardInterface.php#L42)

`public function user(): mixed`

Get the authenticated user, or null if not authenticated.

The shape of the user object is implementation-defined.

**➡️ Return value**

- Type: mixed


---

### id() · [source](../../src/Security/GuardInterface.php#L48)

`public function id(): mixed`

Get the authenticated user's identifier, or null if not
authenticated.

**➡️ Return value**

- Type: mixed


---

### logout() · [source](../../src/Security/GuardInterface.php#L53)

`public function logout(): void`

Log the current user out, clearing any persisted state.

**➡️ Return value**

- Type: void



---

[Back to the Index ⤴](README.md)
