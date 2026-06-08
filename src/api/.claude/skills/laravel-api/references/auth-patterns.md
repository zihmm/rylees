# Authentication Patterns

Concrete auth wiring for this project. The architecture rules (modules, boundaries, contracts) come from the main skill; this covers how authentication is implemented across the middleware stack, token issuance, and error responses.

## Dual-mode Bearer token authentication

Two modes coexist under `Authorization: Bearer <token>`:

| Mode | Token source | Resolution |
|------|-------------|------------|
| Web (Developer Console) | Sanctum personal access token, issued by `POST /auth/login` | Sanctum built-in guard |
| CLI | `users.api_key` — 64-char random string, permanent | `AuthenticateWithApiKey` middleware |

Both modes use the same header; no separate scheme or endpoint is needed for the CLI.

## `AuthenticateWithApiKey` middleware

File: `app/Http/Middleware/AuthenticateWithApiKey.php`

Runs **before** Sanctum's guard. If the bearer token matches a `users.api_key`, it sets `Auth::user()` directly; Sanctum then sees an already-authenticated user and skips its own resolution. If it does not match, the request falls through to Sanctum normally.

```php
<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateWithApiKey
{
    public function handle(Request $request, Closure $next): mixed
    {
        $bearer = $request->bearerToken();

        if ($bearer) {
            $user = User::where('api_key', $bearer)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->first();

            if ($user) {
                Auth::setUser($user);
            }
        }

        return $next($request);
    }
}
```

Register it in `bootstrap/app.php` so it runs on every API route:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(append: [
        \App\Http\Middleware\AuthenticateWithApiKey::class,
    ]);
    // Do NOT call $middleware->statefulApi() — this is a stateless API
})
```

The API must not use session middleware. Remove `StartSession`, `EncryptCookies`, and similar session middlewares from the API route group.

## Token issuance

Sanctum tokens are issued with a 60-minute expiry. Always return `expires_in: 3600` — even though the actual expiry is driven by `SANCTUM_TOKEN_EXPIRATION`, the response value is hardcoded so the client does not need to inspect the environment.

```php
$token = $user->createToken('web', ['*'], now()->addMinutes(60));
// Return $token->plainTextToken as "access_token"
```

Login response shape:

```json
{
  "token_type": "Bearer",
  "access_token": "<sanctum-token>",
  "expires_in": 3600,
  "user": {
    "id": "...",
    "username": "jane@example.com",
    "is_active": true,
    "profile": { "id": "...", "firstname": "Jane", "lastname": "Doe" },
    "organisation": { "id": "...", "name": "Doe Digital GmbH" }
  }
}
```

## Inactive user check

After any guard resolves a user, verify `is_active`. The canonical pattern is a named middleware alias `active` registered in `bootstrap/app.php` and applied to every auth-required route group.

```php
if (!auth()->user()->is_active) {
    return response()->json([
        'message' => 'Account is not activated.',
        'code'    => 'inactive_user',
    ], 403);
}
```

Route groups that require an active user:

```php
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    // protected routes
});
```

Public and registration endpoints must **not** carry the `active` middleware.

## Centralized JSON error shapes

All `4xx` responses must match `{ "message": "...", "code": "..." }`. Wire this in `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
        return response()->json(['message' => 'Unauthenticated.', 'code' => 'unauthenticated'], 401);
    });
    $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
        return response()->json(['message' => 'Forbidden.', 'code' => 'forbidden'], 403);
    });
    $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
        return response()->json(['message' => 'Resource not found.', 'code' => 'not_found'], 404);
    });
    $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
        return response()->json([
            'message' => 'The given data was invalid.',
            'code'    => 'validation_error',
            'errors'  => $e->errors(),
        ], 422);
    });
})
```

## Authorization scoping

Controllers must scope queries to the authenticated user. Returning `404` instead of `403` for records the user does not own is intentional — it avoids disclosing whether a resource exists.

```php
// Return 404 if the customer doesn't belong to the caller
if ($customer->user_id !== auth()->id()) {
    return response()->json(['message' => 'Resource not found.', 'code' => 'not_found'], 404);
}
```

## CORS

Configure in `bootstrap/app.php` or `config/cors.php`:

```php
'allowed_origins'  => ['https://console.rylees.ai', 'https://*.rylees.ai'],
'allowed_methods'  => ['*'],
'allowed_headers'  => ['*'],
'supports_credentials' => false,
```

## Security field exposure rules

- `api_key` must appear only in `GET /users/me`. Never in list responses.
- `projects.token` must appear in single-project responses and the create response. Never in list-level responses.
- Passwords are stored as bcrypt hashes; plaintext must never appear in any response or log.
