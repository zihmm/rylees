# Backend API Component — Implementation Specification

Status: v1 — Authoritative agent implementation guide.

This document is self-contained. Implement the entire Backend API component using only this file. Do not reference any other document.

---

## 1. Overview

The Rylees Backend API is a Laravel 13 modular monolith that serves three types of clients:

- **CLI tool** — authenticated via permanent `api_key` Bearer token; publishes release notes
- **Developer Console** — authenticated via Sanctum session tokens; manages customers, projects, account
- **Public Release History** — no authentication; reads published release notes and requests translations

**Base URL:** `https://api.rylees.ai/v1`

All responses use `Content-Type: application/json`. All primary keys are UUIDs. All timestamps are ISO 8601 UTC (`2026-06-05T10:00:00Z`). Soft-deleted records never appear in any response.

**Modules:** Auth, Account, Customer, Project, ReleaseHistory, AI

---

## 2. Technology Stack & Environment

### PHP and framework

| Concern | Choice |
| ------- | ------ |
| Language | PHP 8.5 |
| Framework | Laravel 13 |
| Database | PostgreSQL 16 |
| Auth | Laravel Sanctum 4 |
| LLM client | `openai-php/client ^0.10` |
| Testing | Pest 3 |

### `composer.json` — `require` section

```json
{
  "require": {
    "php": "^8.5",
    "laravel/framework": "^13.8",
    "laravel/sanctum": "^4.0",
    "openai-php/client": "^0.10"
  },
  "require-dev": {
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-laravel": "^3.0",
    "fakerphp/faker": "^1.23",
    "laravel/pint": "^1.0",
    "mockery/mockery": "^1.6"
  }
}
```

### Required `.env` variables

| Variable | Type | Example | Notes |
| -------- | ---- | ------- | ----- |
| `APP_ENV` | string | `production` | |
| `APP_KEY` | string | `base64:...` | `php artisan key:generate` |
| `APP_URL` | string | `https://api.rylees.ai` | |
| `DB_CONNECTION` | string | `pgsql` | Must be `pgsql` |
| `DB_HOST` | string | `127.0.0.1` | |
| `DB_PORT` | integer | `5432` | |
| `DB_DATABASE` | string | `rylees` | |
| `DB_USERNAME` | string | `rylees_user` | |
| `DB_PASSWORD` | string | `secret` | |
| `OPENAI_API_KEY` | string | `sk-...` | |
| `SANCTUM_TOKEN_EXPIRATION` | integer | `60` | Minutes |
| `MAIL_MAILER` | string | `smtp` | |
| `MAIL_HOST` | string | `smtp.example.com` | |
| `MAIL_PORT` | integer | `587` | |
| `MAIL_USERNAME` | string | `user@example.com` | |
| `MAIL_PASSWORD` | string | `secret` | |
| `MAIL_ENCRYPTION` | string | `tls` | |
| `MAIL_FROM_ADDRESS` | string | `noreply@rylees.ai` | |
| `MAIL_FROM_NAME` | string | `Rylees` | |

---

## 3. Directory Structure

```
src/api/
├── app/
│   ├── Http/
│   │   └── Middleware/
│   │       └── AuthenticateWithApiKey.php
│   ├── Modules/
│   │   ├── Auth/
│   │   │   ├── Controllers/
│   │   │   │   └── AuthController.php
│   │   │   ├── Mail/
│   │   │   │   └── AccountActivationMail.php
│   │   │   ├── Requests/
│   │   │   │   └── LoginRequest.php
│   │   │   ├── Services/
│   │   │   │   └── AuthService.php
│   │   │   └── routes.php
│   │   ├── Account/
│   │   │   ├── Controllers/
│   │   │   │   └── AccountController.php
│   │   │   ├── Repositories/
│   │   │   │   └── AccountRepository.php
│   │   │   ├── Requests/
│   │   │   │   ├── RegisterRequest.php
│   │   │   │   └── UpdateAccountRequest.php
│   │   │   ├── Resources/
│   │   │   │   └── UserResource.php
│   │   │   ├── Services/
│   │   │   │   └── AccountService.php
│   │   │   └── routes.php
│   │   ├── Customer/
│   │   │   ├── Controllers/
│   │   │   │   ├── CustomerController.php
│   │   │   │   └── ContactController.php
│   │   │   ├── Models/
│   │   │   │   ├── Customer.php
│   │   │   │   └── CustomerContact.php
│   │   │   ├── Repositories/
│   │   │   │   ├── CustomerRepository.php
│   │   │   │   └── ContactRepository.php
│   │   │   ├── Requests/
│   │   │   │   ├── ListCustomersRequest.php
│   │   │   │   ├── StoreCustomerRequest.php
│   │   │   │   ├── UpdateCustomerRequest.php
│   │   │   │   ├── StoreContactRequest.php
│   │   │   │   └── UpdateContactRequest.php
│   │   │   ├── Resources/
│   │   │   │   ├── CustomerListResource.php
│   │   │   │   ├── CustomerDetailResource.php
│   │   │   │   └── ContactResource.php
│   │   │   ├── Services/
│   │   │   │   └── CustomerService.php
│   │   │   └── routes.php
│   │   ├── Project/
│   │   │   ├── Controllers/
│   │   │   │   └── ProjectController.php
│   │   │   ├── Models/
│   │   │   │   └── Project.php
│   │   │   ├── Repositories/
│   │   │   │   └── ProjectRepository.php
│   │   │   ├── Requests/
│   │   │   │   ├── StoreProjectRequest.php
│   │   │   │   └── UpdateProjectRequest.php
│   │   │   ├── Resources/
│   │   │   │   ├── ProjectListResource.php
│   │   │   │   └── ProjectDetailResource.php
│   │   │   ├── Services/
│   │   │   │   └── ProjectService.php
│   │   │   └── routes.php
│   │   ├── ReleaseHistory/
│   │   │   ├── Controllers/
│   │   │   │   ├── ReleaseHistoryController.php
│   │   │   │   └── PublicReleaseHistoryController.php
│   │   │   ├── Models/
│   │   │   │   ├── ReleaseHistory.php
│   │   │   │   └── ReleaseNote.php
│   │   │   ├── Repositories/
│   │   │   │   └── ReleaseHistoryRepository.php
│   │   │   ├── Requests/
│   │   │   │   └── PublishReleaseNoteRequest.php
│   │   │   ├── Resources/
│   │   │   │   └── ReleaseNoteResource.php
│   │   │   ├── Services/
│   │   │   │   └── ReleaseHistoryService.php
│   │   │   └── routes.php
│   │   └── AI/
│   │       ├── Services/
│   │       │   └── TranslationService.php
│   │       └── routes.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── UserProfile.php
│   │   └── Organisation.php
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── ModuleServiceProvider.php
├── database/
│   ├── migrations/
│   │   ├── 2026_01_01_000001_create_organisations_table.php
│   │   ├── 2026_01_01_000002_create_industry_types_table.php
│   │   ├── 2026_01_01_000003_create_llm_tonality_types_table.php
│   │   ├── 2026_01_01_000004_create_llm_temperature_types_table.php
│   │   ├── 2026_01_01_000005_create_users_table.php
│   │   ├── 2026_01_01_000006_create_user_profiles_table.php
│   │   ├── 2026_01_01_000007_create_customers_table.php
│   │   ├── 2026_01_01_000008_create_customer_contacts_table.php
│   │   ├── 2026_01_01_000009_create_projects_table.php
│   │   ├── 2026_01_01_000010_create_release_histories_table.php
│   │   └── 2026_01_01_000011_create_release_notes_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── LlmTonalityTypeSeeder.php
│       ├── LlmTemperatureTypeSeeder.php
│       └── IndustryTypeSeeder.php
└── routes/
    └── api.php
```

---

## 4. Routing and Middleware

### `bootstrap/app.php` — routing

```php
->withRouting(
    api: __DIR__ . '/../routes/api.php',
    apiPrefix: '',
    health: '/up',
)
```

### `routes/api.php`

```php
<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require __DIR__ . '/../app/Modules/Auth/routes.php';
    require __DIR__ . '/../app/Modules/Account/routes.php';
    require __DIR__ . '/../app/Modules/Customer/routes.php';
    require __DIR__ . '/../app/Modules/Project/routes.php';
    require __DIR__ . '/../app/Modules/ReleaseHistory/routes.php';
    require __DIR__ . '/../app/Modules/AI/routes.php';
});
```

### Middleware stack (API group)

All API routes (under `v1`) must use the following middleware, configured in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(append: [
        \App\Http\Middleware\AuthenticateWithApiKey::class,
    ]);
    $middleware->statefulApi(); // NOT called — this is a stateless API
})
```

The API must NOT use session middleware. Remove `StartSession`, `EncryptCookies`, and similar session middlewares from the API route group.

### CORS

Configure in `bootstrap/app.php` or `config/cors.php`:

```php
'allowed_origins' => ['https://console.rylees.ai', 'https://*.rylees.ai'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => false,
```

### Response headers

Every response must include `Content-Type: application/json`. Enforce by returning `JsonResponse` from all controllers and configuring the exception handler accordingly.

---

## 5. Authentication System

### 5.1 Dual-mode Authentication

Two authentication modes coexist. Both use `Authorization: Bearer <token>`.

**Mode 1 — Web (Developer Console):** Standard Sanctum personal access token, issued by `POST /auth/login`. Resolved by Sanctum's built-in token guard.

**Mode 2 — CLI:** The developer's `users.api_key` (64-char random string, permanent). Resolved by a custom middleware.

#### `AuthenticateWithApiKey` middleware

File: `app/Http/Middleware/AuthenticateWithApiKey.php`

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

This middleware runs before Sanctum resolves its token. If the `api_key` matches, `Auth::user()` is set and Sanctum's guard sees an already-authenticated user and skips its own resolution. If the `api_key` does not match, the request passes through to Sanctum's standard token resolution.

### 5.2 Token Issuance

```php
$token = $user->createToken('web', ['*'], now()->addMinutes(60));
```

Return `$token->plainTextToken` in the login response as `access_token`. Always return `"expires_in": 3600`.

### 5.3 Inactive User Check

After any auth guard resolves a user, check `is_active`. Implement this as a middleware or inside each auth-required controller's base logic:

```php
if (!auth()->user()->is_active) {
    return response()->json([
        'message' => 'Account is not activated.',
        'code' => 'inactive_user',
    ], 403);
}
```

Alternatively, register a middleware `EnsureUserIsActive` and append it to all auth-required routes.

### 5.4 Exception Handler — Error Shapes

In `bootstrap/app.php`:

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
            'code' => 'validation_error',
            'errors' => $e->errors(),
        ], 422);
    });
})
```

All `4xx` error responses MUST match `{ "message": "...", "code": "..." }`.

---

## 6. Database Schema

All tables use UUID primary keys. All tables except `*_types` lookup tables include `created_at`, `updated_at`, `deleted_at` (soft delete). Create migrations in dependency order.

### Migration 1: `organisations`

```php
Schema::create('organisations', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('slug', 255)->unique()->notNull();
    $table->string('name', 255)->notNull();
    $table->string('street', 255)->nullable();
    $table->string('postcode', 20)->nullable();
    $table->string('city', 255)->nullable();
    $table->string('website', 255)->nullable();
    $table->string('email', 255)->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### Migration 2: `industry_types`

```php
Schema::create('industry_types', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name', 255)->unique()->notNull();
});
```

### Migration 3: `llm_tonality_types`

```php
Schema::create('llm_tonality_types', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name', 255)->unique()->notNull();
});
```

### Migration 4: `llm_temperature_types`

```php
Schema::create('llm_temperature_types', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name', 255)->unique()->notNull();
    $table->float('value')->notNull();
});
```

### Migration 5: `users`

```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('username', 255)->unique()->notNull();
    $table->string('password', 255)->notNull();
    $table->string('api_key', 64)->unique()->notNull();
    $table->boolean('is_active')->default(false)->notNull();
    $table->string('activation_token', 255)->nullable();
    $table->timestamp('activated_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### Migration 6: `user_profiles`

```php
Schema::create('user_profiles', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('user_id')->unique()->notNull();
    $table->string('firstname', 255)->notNull();
    $table->string('lastname', 255)->notNull();
    $table->uuid('organisation_id')->notNull();
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('user_id')->references('id')->on('users');
    $table->foreign('organisation_id')->references('id')->on('organisations');
    $table->index('organisation_id', 'user_profiles_organisation_id_index');
});
```

### Migration 7: `customers`

Create `main_contact_id` as nullable without FK yet (contacts don't exist); the FK is added in migration 8.

```php
Schema::create('customers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('user_id')->notNull();
    $table->uuid('organisation_id')->notNull();
    $table->uuid('industry_id')->nullable();
    $table->uuid('main_contact_id')->nullable();
    $table->text('description')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('user_id')->references('id')->on('users');
    $table->foreign('organisation_id')->references('id')->on('organisations');
    $table->foreign('industry_id')->references('id')->on('industry_types');

    $table->index('user_id', 'customers_user_id_index');
    $table->index('organisation_id', 'customers_organisation_id_index');
    $table->index('industry_id', 'customers_industry_id_index');
    $table->index('main_contact_id', 'customers_main_contact_id_index');
});
```

### Migration 8: `customer_contacts` + add FK on `customers`

```php
Schema::create('customer_contacts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('customer_id')->notNull();
    $table->string('firstname', 255)->notNull();
    $table->string('lastname', 255)->notNull();
    $table->string('email', 255)->notNull();
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('customer_id')->references('id')->on('customers');
    $table->index('customer_id', 'customer_contacts_customer_id_index');
});

// Add FK now that customer_contacts exists
Schema::table('customers', function (Blueprint $table) {
    $table->foreign('main_contact_id')->references('id')->on('customer_contacts');
});
```

### Migration 9: `projects`

```php
Schema::create('projects', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('customer_id')->notNull();
    $table->string('name', 255)->notNull();
    $table->string('key', 255)->notNull();
    $table->text('description')->nullable();
    $table->string('token', 64)->unique()->notNull();
    $table->uuid('llm_tonality_id')->notNull();
    $table->uuid('llm_temperature_id')->notNull();
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('customer_id')->references('id')->on('customers');
    $table->foreign('llm_tonality_id')->references('id')->on('llm_tonality_types');
    $table->foreign('llm_temperature_id')->references('id')->on('llm_temperature_types');

    $table->index('customer_id', 'projects_customer_id_index');
    $table->index('llm_tonality_id', 'projects_llm_tonality_id_index');
    $table->index('llm_temperature_id', 'projects_llm_temperature_id_index');

    // Composite unique: key is unique per customer
    $table->unique(['customer_id', 'key']);
});
```

### Migration 10: `release_histories`

```php
Schema::create('release_histories', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('project_id')->notNull();
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('project_id')->references('id')->on('projects');
    $table->index('project_id', 'release_histories_project_id_index');
});
```

### Migration 11: `release_notes`

There is **no `type` column** on this table. The `type` value from the CLI publish request only determines which pair of columns to populate.

```php
Schema::create('release_notes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('release_history_id')->notNull();
    $table->uuid('author_id')->notNull();
    $table->text('body')->notNull();
    $table->integer('version_major')->notNull();
    $table->integer('version_minor')->notNull();
    $table->integer('version_patch')->notNull();
    $table->string('branch_name', 255)->nullable();
    $table->char('commithash_start', 64)->nullable();
    $table->char('commithash_end', 64)->nullable();
    $table->string('tag_start', 255)->nullable();
    $table->string('tag_end', 255)->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('release_history_id')->references('id')->on('release_histories');
    $table->foreign('author_id')->references('id')->on('users');

    $table->index('release_history_id', 'release_notes_release_history_id_index');
    $table->index('author_id', 'release_notes_author_id_index');
});
```

### Slug generation — `HasSlug` trait

Implement as a trait used by `Organisation` and `Project` models.

```php
// app/Models/Concerns/HasSlug.php
namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function (Model $model) {
            $model->setAttribute(
                static::slugColumn(),
                static::generateUniqueSlug($model->getAttribute(static::slugSource()), $model)
            );
        });
    }

    protected static function slugColumn(): string
    {
        return 'slug';
    }

    protected static function slugSource(): string
    {
        return 'name';
    }

    protected static function generateUniqueSlug(string $source, Model $model): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '-', strtolower($source));
        $base = trim($base, '-');
        $slug = $base;
        $i = 2;
        while (static::slugExists($slug, $model)) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    protected static function slugExists(string $slug, Model $model): bool
    {
        $query = static::where(static::slugColumn(), $slug);
        // For Project: scope to customer_id
        if (method_exists(static::class, 'slugScope')) {
            $query = static::slugScope($query, $model);
        }
        if ($model->exists) {
            $query->whereKeyNot($model->getKey());
        }
        return $query->exists();
    }
}
```

`Organisation` uses the default (global uniqueness). `Project` overrides `slugColumn()` to return `'key'` and overrides `slugScope()` to scope to `customer_id`.

```php
// In Project model
protected static function slugColumn(): string { return 'key'; }
protected static function slugScope($query, $model) {
    return $query->where('customer_id', $model->customer_id);
}
```

---

## 7. Module Specifications

Modules MUST communicate only through public service class interfaces. Direct cross-module Model access is PROHIBITED.

### 7.1 Auth Module

**`Auth/routes.php`**

```php
Route::post('/auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/auth/logout', [AuthController::class, 'logout']);
```

**`AuthController`**

```php
public function login(LoginRequest $request): JsonResponse
public function logout(Request $request): JsonResponse  // 204
```

**`LoginRequest` validation rules**

```php
'username' => 'required|email',
'password' => 'required|string',
```

**`AuthService`**

```php
public function login(array $credentials): array
// - Find user by username; if not found → throw AuthenticationException
// - Hash::check($credentials['password'], $user->password); if false → throw AuthenticationException
// - If not is_active → return 403 inactive_user (throw or return special value — handled in controller)
// - Create token: $user->createToken('web', ['*'], now()->addMinutes(60))
// - Return array with token_type, access_token, expires_in, user shape

public function logout(User $user): void
// - $user->currentAccessToken()->delete()
```

**Login response shape:**

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

**`AccountActivationMail`**

File: `Auth/Mail/AccountActivationMail.php`

```php
class AccountActivationMail extends Mailable
{
    public function __construct(public User $user) {}

    public function build(): self
    {
        return $this->subject('Activate your Rylees account')
            ->markdown('emails.account-activation');
    }
}
```

Activation link format:
```
https://console.rylees.ai/activate?token={activation_token}
```

Email blade template must include:
- Greeting: `"Hello {firstname}!"`
- Explanation: account activation is required before login
- A prominent button linking to the activation URL
- Plain-text fallback

---

### 7.2 Account Module

**`Account/routes.php`**

```php
Route::post('/users/register', [AccountController::class, 'register']);
Route::get('/users/activate', [AccountController::class, 'activate']);
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/users/me', [AccountController::class, 'me']);
    Route::patch('/users/me', [AccountController::class, 'update']);
    Route::delete('/users/me', [AccountController::class, 'destroy']);
});
```

> `active` middleware checks `is_active`; configure it or inline the check.

**`AccountController`**

```php
public function register(RegisterRequest $request): JsonResponse    // 201
public function activate(Request $request): JsonResponse             // 200
public function me(Request $request): JsonResponse                   // 200
public function update(UpdateAccountRequest $request): JsonResponse  // 200
public function destroy(Request $request): JsonResponse              // 204
```

**`RegisterRequest` validation rules**

```php
'username'             => 'required|email|unique:users,username',
'password'             => 'required|string|min:12',
'profile.firstname'    => 'required|string',
'profile.lastname'     => 'required|string',
'organisation.name'    => 'required|string',
'organisation.street'  => 'nullable|string',
'organisation.postcode'=> 'nullable|string',
'organisation.city'    => 'nullable|string',
'organisation.website' => 'nullable|string',
'organisation.email'   => 'nullable|email',
```

**`AccountService`**

```php
public function register(array $data): array
// 1. Create Organisation (HasSlug generates slug from name)
// 2. Create User: username, bcrypt(password), api_key=Str::random(64), is_active=false, activation_token=Str::random(64)
// 3. Create UserProfile: user_id, firstname, lastname, organisation_id
// 4. Dispatch AccountActivationMail to username address
// 5. Return { user, profile, organisation } response array

public function activate(string $token): void
// - Find User where activation_token = $token and activation_token IS NOT NULL
// - If not found: throw ModelNotFoundException
// - Set is_active = true, activated_at = now(), activation_token = null, save

public function updateMe(User $user, array $data): array
// - If profile fields present: update UserProfile
// - If organisation fields present: update Organisation via user->profile->organisation
// - If new_password: verify current_password via Hash::check; if wrong → ValidationException on current_password; update password
// - Return full user shape (same as GET /users/me)

public function destroyMe(User $user): void
// - $user->tokens()->delete()
// - $user->profile->delete() (soft)
// - $user->delete() (soft)
// - Do NOT delete the Organisation
```

**`UpdateAccountRequest` validation rules**

```php
'profile.firstname'    => 'sometimes|string',
'profile.lastname'     => 'sometimes|string',
'organisation.name'    => 'sometimes|string',
'organisation.street'  => 'sometimes|nullable|string',
'organisation.postcode'=> 'sometimes|nullable|string',
'organisation.city'    => 'sometimes|nullable|string',
'organisation.website' => 'sometimes|nullable|string',
'organisation.email'   => 'sometimes|nullable|email',
'current_password'     => 'required_with:new_password|string',
'new_password'         => 'sometimes|string|min:12',
```

**`GET /users/me` response shape**

```json
{
  "id": "...",
  "username": "jane@example.com",
  "is_active": true,
  "activated_at": "...",
  "api_key": "<64-char-api-key>",
  "profile": { "id": "...", "firstname": "Jane", "lastname": "Doe" },
  "organisation": {
    "id": "...",
    "slug": "doe-digital-gmbh",
    "name": "Doe Digital GmbH",
    "street": "...",
    "postcode": "...",
    "city": "...",
    "website": "...",
    "email": "..."
  }
}
```

**`POST /users/register` response shape (201)**

```json
{
  "user": {
    "id": "...",
    "username": "...",
    "is_active": false,
    "activated_at": null,
    "created_at": "..."
  },
  "profile": { "id": "...", "firstname": "Jane", "lastname": "Doe" },
  "organisation": { "id": "...", "name": "Doe Digital GmbH", "slug": "doe-digital-gmbh" }
}
```

---

### 7.3 Customer Module

**`Customer/routes.php`**

```php
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);
    Route::patch('/customers/{customer}', [CustomerController::class, 'update']);

    Route::post('/customers/{customer}/contacts', [ContactController::class, 'store']);
    Route::patch('/customers/{customer}/contacts/{contact}', [ContactController::class, 'update']);
    Route::delete('/customers/{customer}/contacts/{contact}', [ContactController::class, 'destroy']);
});
```

**`CustomerController`**

```php
public function index(ListCustomersRequest $request): JsonResponse   // 200 paginated
public function store(StoreCustomerRequest $request): JsonResponse   // 201
public function show(Request $request, Customer $customer): JsonResponse // 200
public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse // 200
```

**Authorization on all customer endpoints:** Verify `$customer->user_id === auth()->id()`. If not, return `404 not_found` (do not expose existence to other users).

**`ListCustomersRequest` validation rules**

```php
'page'     => 'sometimes|integer|min:1',
'per_page' => 'sometimes|integer|min:1|max:100',
```

**`CustomerRepository`**

```php
public function paginatedForUser(User $user, int $page, int $perPage): LengthAwarePaginator
// Query: customers where user_id = $user->id, no soft-deleted, load organisation + main_contact + industry
// Also load projects_count via withCount('projects')
// paginate($perPage, ['*'], 'page', $page)

public function findForUser(string $id, User $user): ?Customer
// Find by id where user_id = $user->id, load all relations
```

**`GET /customers` paginated response shape**

```json
{
  "data": [
    {
      "id": "...",
      "description": "...",
      "projects_count": 3,
      "organisation": {
        "id": "...",
        "slug": "acme-ltd",
        "name": "Acme Ltd.",
        "street": "...",
        "postcode": "...",
        "city": "...",
        "website": "...",
        "email": "..."
      },
      "main_contact": {
        "id": "...",
        "firstname": "John",
        "lastname": "Doe",
        "email": "john@acme.com"
      },
      "industry": { "id": "...", "name": "Architecture" },
      "created_at": "...",
      "updated_at": "..."
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "total": 42
  }
}
```

> `main_contact` is `null` if no main contact is set. Use `CustomerListResource` to shape the response.

**`StoreCustomerRequest` validation rules**

```php
'organisation.name'      => 'required|string',
'organisation.street'    => 'nullable|string',
'organisation.postcode'  => 'nullable|string',
'organisation.city'      => 'nullable|string',
'organisation.website'   => 'nullable|string',
'organisation.email'     => 'nullable|email',
'industry_id'            => 'nullable|uuid|exists:industry_types,id',
'description'            => 'nullable|string',
'main_contact.firstname' => 'required_with:main_contact|string',
'main_contact.lastname'  => 'required_with:main_contact|string',
'main_contact.email'     => 'required_with:main_contact|email',
```

**`CustomerService::store` creation order:**

1. Create `Organisation` (slug auto-generated by `HasSlug`)
2. Create `Customer` with `user_id = auth()->id()`, `organisation_id`, `industry_id`, `description`
3. If `main_contact` data present: create `CustomerContact` → set `customer.main_contact_id = contact.id` → save customer
4. Return `{ id, organisation: { id, name, slug }, created_at }`

**`UpdateCustomerRequest` validation rules**

```php
'organisation.name'     => 'sometimes|string',
'organisation.street'   => 'sometimes|nullable|string',
'organisation.postcode' => 'sometimes|nullable|string',
'organisation.city'     => 'sometimes|nullable|string',
'organisation.website'  => 'sometimes|nullable|string',
'organisation.email'    => 'sometimes|nullable|email',
'industry_id'           => 'sometimes|nullable|uuid|exists:industry_types,id',
'description'           => 'sometimes|nullable|string',
```

Contacts are NOT updatable through this endpoint. PATCH /customers/{id} does not accept a `contacts` or `main_contact` field.

**`GET /customers/{id}` response shape**

```json
{
  "id": "...",
  "description": "...",
  "organisation": {
    "id": "...",
    "slug": "acme-ltd",
    "name": "Acme Ltd.",
    "street": "...",
    "postcode": "...",
    "city": "...",
    "website": "...",
    "email": "..."
  },
  "industry": { "id": "...", "name": "Architecture" },
  "contacts": [
    { "id": "...", "firstname": "John", "lastname": "Doe", "email": "john@acme.com" }
  ],
  "main_contact": {
    "id": "...",
    "firstname": "John",
    "lastname": "Doe",
    "email": "john@acme.com"
  },
  "projects": [
    { "id": "...", "name": "Member Portal", "key": "member-portal" }
  ]
}
```

**Contact CRUD (`ContactController`)**

```php
public function store(StoreContactRequest $request, Customer $customer): JsonResponse // 201
public function update(UpdateContactRequest $request, Customer $customer, CustomerContact $contact): JsonResponse // 200
public function destroy(Request $request, Customer $customer, CustomerContact $contact): JsonResponse // 204
```

Authorization: verify `$customer->user_id === auth()->id()`. Verify `$contact->customer_id === $customer->id`.

`StoreContactRequest` rules:
```php
'firstname' => 'required|string',
'lastname'  => 'required|string',
'email'     => 'required|email',
```

`UpdateContactRequest` rules:
```php
'firstname' => 'sometimes|string',
'lastname'  => 'sometimes|string',
'email'     => 'sometimes|email',
```

`DELETE /customers/{id}/contacts/{contactId}` logic:
1. Soft-delete the `CustomerContact` row
2. If `$customer->main_contact_id === $contact->id`: set `$customer->main_contact_id = null` and save

Contact response shape (201 and 200):
```json
{ "id": "...", "firstname": "Jane", "lastname": "Smith", "email": "jane@acme.com" }
```

---

### 7.4 Project Module

**`Project/routes.php`**

```php
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/customers/{customer}/projects', [ProjectController::class, 'index']);
    Route::post('/customers/{customer}/projects', [ProjectController::class, 'store']);
    Route::get('/customers/{customer}/projects/{project}', [ProjectController::class, 'show']);
    Route::patch('/customers/{customer}/projects/{project}', [ProjectController::class, 'update']);
});
```

**`ProjectController`**

```php
public function index(Request $request, Customer $customer): JsonResponse // 200
public function store(StoreProjectRequest $request, Customer $customer): JsonResponse // 201
public function show(Request $request, Customer $customer, Project $project): JsonResponse // 200
public function update(UpdateProjectRequest $request, Customer $customer, Project $project): JsonResponse // 200
```

Authorization: verify `$customer->user_id === auth()->id()`. For project endpoints also verify `$project->customer_id === $customer->id`.

**`StoreProjectRequest` validation rules**

```php
'name'               => 'required|string',
'description'        => 'nullable|string',
'llm_tonality_id'    => 'required|uuid|exists:llm_tonality_types,id',
'llm_temperature_id' => 'required|uuid|exists:llm_temperature_types,id',
```

**`ProjectService::store` logic:**

1. Generate `key` via `HasSlug` (scoped to `customer_id`)
2. Generate `token = Str::random(64)`
3. Create `Project`
4. Create `ReleaseHistory` with `project_id = $project->id`
5. Return create response

**`POST /customers/{id}/projects` response shape (201)**

```json
{
  "id": "...",
  "name": "Member Portal",
  "key": "member-portal",
  "token": "<64-char-token>",
  "created_at": "..."
}
```

**`GET /customers/{id}/projects` response shape**

```json
{
  "data": [
    {
      "id": "...",
      "name": "Member Portal",
      "key": "member-portal",
      "description": "...",
      "token": "<64-char-token>",
      "llm": { "temperature": 0.5, "tonality": "professional" },
      "created_at": "..."
    }
  ]
}
```

**`GET /customers/{id}/projects/{projectId}` response shape**

```json
{
  "id": "...",
  "name": "Member Portal",
  "key": "member-portal",
  "description": "...",
  "token": "<64-char-token>",
  "customer": {
    "id": "...",
    "name": "Acme Ltd.",
    "industry": "Architecture",
    "organisation_slug": "acme-ltd"
  },
  "llm": { "temperature": 0.5, "tonality": "professional" },
  "created_at": "...",
  "updated_at": "..."
}
```

> `customer.organisation_slug` is the `organisations.slug` of the customer's organisation. The frontend uses this to call `GET /public/release-history/{organisation_slug}/{project.key}`.

**`UpdateProjectRequest` validation rules**

```php
'name'               => 'sometimes|string',
'description'        => 'sometimes|nullable|string',
'llm_tonality_id'    => 'sometimes|uuid|exists:llm_tonality_types,id',
'llm_temperature_id' => 'sometimes|uuid|exists:llm_temperature_types,id',
```

`token` and `key` MUST NOT be updated via PATCH. Do not accept these fields; ignore them silently or add them to the `$guarded` array on the `Project` model.

**`UpdateProjectRequest` response shape (200)**

Same shape as `GET /customers/{id}/projects/{projectId}`.

---

### 7.5 ReleaseHistory Module — CLI Publish Endpoint

**`ReleaseHistory/routes.php`**

```php
// CLI endpoint — authenticated via api_key Bearer token (AuthenticateWithApiKey middleware)
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/projects/{projectToken}/release-history', [ReleaseHistoryController::class, 'publish']);
});

// Public endpoints — no auth
Route::prefix('public')->group(function () {
    Route::get('/release-history/{customerSlug}/{projectKey}', [PublicReleaseHistoryController::class, 'index']);
    Route::get('/release-history/{customerSlug}/{projectKey}/translate', [PublicReleaseHistoryController::class, 'translate']);
});

// Reference data — no auth
Route::prefix('ref')->group(function () {
    Route::get('/industries', fn() => response()->json(['items' => \App\Modules\ReleaseHistory\...]) );
    // see section 7.7 for full ref endpoints
});
```

**`ReleaseHistoryController::publish`**

```php
public function publish(PublishReleaseNoteRequest $request, string $projectToken): JsonResponse
```

**Lookup and authorization:**

```php
$project = Project::where('token', $projectToken)->firstOrFail();
// Verify ownership through customer
if ($project->customer->user_id !== auth()->id()) {
    return response()->json(['message' => 'Forbidden.', 'code' => 'forbidden'], 403);
}
$history = $project->releaseHistory; // 1:1 relationship
```

**`PublishReleaseNoteRequest` validation rules**

```php
'startRef'    => 'required|string',
'endRef'      => 'required|string',
'type'        => 'required|in:commits,tag',
'branchName'  => 'nullable|string',
'body'        => 'required|string',
'versionBump' => 'required|in:major,minor,patch',
```

**Version computation (exact algorithm — implement verbatim)**

```php
$latest = $history->releaseNotes()
    ->whereNull('deleted_at')
    ->orderByDesc('created_at')
    ->first();

if ($latest === null) {
    $major = 0; $minor = 0; $patch = 0;
} else {
    $major = $latest->version_major;
    $minor = $latest->version_minor;
    $patch = $latest->version_patch;
}

switch ($request->versionBump) {
    case 'major': $major++; $minor = 0; $patch = 0; break;
    case 'minor': $minor++; $patch = 0; break;
    case 'patch': $patch++; break;
}
```

**Column population logic:**

```php
$note = new ReleaseNote([
    'release_history_id' => $history->id,
    'author_id'          => auth()->id(),
    'body'               => $request->body,
    'version_major'      => $major,
    'version_minor'      => $minor,
    'version_patch'      => $patch,
    'branch_name'        => $request->branchName ?? null,
    'commithash_start'   => $request->type === 'commits' ? $request->startRef : null,
    'commithash_end'     => $request->type === 'commits' ? $request->endRef : null,
    'tag_start'          => $request->type === 'tag' ? $request->startRef : null,
    'tag_end'            => $request->type === 'tag' ? $request->endRef : null,
]);
$note->save();
```

**Response `201 Created`**

```json
{
  "id": "...",
  "status": "published",
  "version": "0.1.0"
}
```

Version string format: `"{major}.{minor}.{patch}"`.

---

### 7.6 Public Release History Module

**`PublicReleaseHistoryController::index`**

Resolve slug → customer → project:

```php
$organisation = Organisation::where('slug', $customerSlug)->whereNull('deleted_at')->firstOrFail();
$customer = Customer::where('organisation_id', $organisation->id)->whereNull('deleted_at')->firstOrFail();
$project = Project::where('customer_id', $customer->id)
    ->where('key', $projectKey)
    ->whereNull('deleted_at')
    ->firstOrFail();
$history = $project->releaseHistory;
$notes = $history->releaseNotes()
    ->whereNull('deleted_at')
    ->orderByDesc('created_at')
    ->get();
```

**`GET /public/release-history/{customerSlug}/{projectKey}` response shape**

```json
{
  "project": { "id": "...", "name": "Member Portal", "key": "member-portal" },
  "items": [
    {
      "id": "...",
      "version": "1.3.0",
      "body": "Diese Version enthält...",
      "publishedAt": "2026-06-05T12:00:00Z"
    }
  ]
}
```

`version` = `"{version_major}.{version_minor}.{version_patch}"`. `publishedAt` = `created_at` in ISO 8601 UTC.

Returns `404 not_found` (via `firstOrFail()`) if slug/key does not resolve.

**`PublicReleaseHistoryController::translate`**

```php
public function translate(Request $request, string $customerSlug, string $projectKey): JsonResponse
```

Validate:
```php
$request->validate(['language' => 'required|in:de,en,fr']);
```

If validation fails: `422 validation_error`.

Resolve project same as `index`. Pass notes to `TranslationService`:

```php
$translated = $this->translationService->translate(
    $notes->map(fn($n) => ['id' => $n->id, 'body' => $n->body])->toArray(),
    $request->language
);
return response()->json(['language' => $request->language, 'items' => $translated]);
```

**`GET /public/release-history/{customerSlug}/{projectKey}/translate` response shape**

```json
{
  "language": "fr",
  "items": [
    { "id": "...", "version": "1.3.0", "body": "Cette version inclut..." }
  ]
}
```

---

### 7.7 AI Module — Translation Service

**`AI/Services/TranslationService.php`**

Uses `openai-php/client` directly (NOT LangChain).

```php
<?php

namespace App\Modules\AI\Services;

use OpenAI;

class TranslationService
{
    private \OpenAI\Client $client;

    public function __construct()
    {
        $this->client = OpenAI::client(config('services.openai.key'));
    }

    public function translate(array $notes, string $targetLanguage): array
    {
        $systemPrompt = <<<PROMPT
You are translating release notes from German into {$targetLanguage}.
The audience is non-technical.
Return a JSON array where each object has exactly two keys: "id" and "body".
Translate only the "body" value. Do not alter IDs, version numbers, or dates.
Return only the JSON array, nothing else.
PROMPT;

        $userMessage = json_encode($notes, JSON_UNESCAPED_UNICODE);

        $response = $this->client->chat()->create([
            'model'       => 'GPT-5.4',
            'temperature' => 0.3,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        $content = $response->choices[0]->message->content;
        return json_decode($content, true);
    }
}
```

Configure OpenAI key in `config/services.php`:
```php
'openai' => ['key' => env('OPENAI_API_KEY')],
```

---

### 7.8 Reference Data Endpoints

Add to `ReleaseHistory/routes.php` (or a dedicated `Ref/routes.php`):

```php
Route::prefix('ref')->group(function () {
    Route::get('/industries', function () {
        return response()->json(['items' => \App\Models\IndustryType::orderBy('name')->get(['id', 'name'])]);
    });
    Route::get('/llm-tonalities', function () {
        return response()->json(['items' => \App\Models\LlmTonalityType::orderBy('name')->get(['id', 'name'])]);
    });
    Route::get('/llm-temperatures', function () {
        return response()->json(['items' => \App\Models\LlmTemperatureType::orderBy('name')->get(['id', 'name', 'value'])]);
    });
});
```

No authentication required on any reference endpoint.

Response shape example for `GET /ref/industries`:
```json
{ "items": [{ "id": "...", "name": "Architecture" }, ...] }
```

---

## 8. Seed Data

Run with `php artisan db:seed`. All seeders must be idempotent using `updateOrInsert`.

### `LlmTonalityTypeSeeder`

```php
$tonalities = ['neutral', 'professional', 'friendly', 'humorous'];
foreach ($tonalities as $name) {
    DB::table('llm_tonality_types')->updateOrInsert(
        ['name' => $name],
        ['id' => (string) Str::uuid(), 'name' => $name]
    );
}
```

> Use `updateOrInsert` with `name` as the match key. The `id` column is only inserted on first run; `updateOrInsert` skips update if the row exists.

### `LlmTemperatureTypeSeeder`

```php
$temperatures = [
    ['name' => 'precise',  'value' => 0.2],
    ['name' => 'balanced', 'value' => 0.5],
    ['name' => 'creative', 'value' => 0.8],
];
foreach ($temperatures as $item) {
    DB::table('llm_temperature_types')->updateOrInsert(
        ['name' => $item['name']],
        ['id' => (string) Str::uuid(), 'name' => $item['name'], 'value' => $item['value']]
    );
}
```

### `IndustryTypeSeeder`

13 entries:

```php
$industries = [
    'Architecture', 'Consulting', 'Education', 'Finance', 'Healthcare',
    'Legal', 'Manufacturing', 'Marketing', 'Media', 'Real Estate',
    'Retail', 'Technology', 'Other',
];
foreach ($industries as $name) {
    DB::table('industry_types')->updateOrInsert(
        ['name' => $name],
        ['id' => (string) Str::uuid(), 'name' => $name]
    );
}
```

### `DatabaseSeeder`

```php
public function run(): void
{
    $this->call([
        LlmTonalityTypeSeeder::class,
        LlmTemperatureTypeSeeder::class,
        IndustryTypeSeeder::class,
    ]);
}
```

---

## 9. Server Configuration — nginx

Three DNS records for `rylees.ai`:

| DNS record | Type | Purpose |
| ---------- | ---- | ------- |
| `api.rylees.ai` | A | API server |
| `console.rylees.ai` | A | Developer Console SPA |
| `*.rylees.ai` | A (wildcard) | Customer Release History subdomains |

Specific records take precedence over the wildcard. The wildcard TLS certificate covers `*.rylees.ai`. `api.rylees.ai` and `console.rylees.ai` use separate certificates.

### Server block 1 — API

```nginx
server {
    listen 443 ssl http2;
    server_name api.rylees.ai;
    root /var/www/api/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    ssl_certificate /etc/letsencrypt/live/api.rylees.ai/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.rylees.ai/privkey.pem;
}
```

### Server block 2 — Developer Console

```nginx
server {
    listen 443 ssl http2;
    server_name console.rylees.ai;
    root /var/www/frontend/dist;
    index console.html;

    location / {
        try_files $uri /console.html;
    }

    ssl_certificate /etc/letsencrypt/live/console.rylees.ai/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/console.rylees.ai/privkey.pem;
}
```

### Server block 3 — Wildcard customer subdomains

```nginx
server {
    listen 443 ssl http2;
    server_name ~^(?<subdomain>.+)\.rylees\.ai$;

    if ($subdomain = "console") { return 404; }
    if ($subdomain = "api") { return 404; }

    root /var/www/frontend/dist;
    index history.html;

    location / {
        try_files $uri /history.html;
    }

    ssl_certificate /etc/letsencrypt/live/rylees.ai/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/rylees.ai/privkey.pem;
}
```

No DNS API call or provisioning step is required when a customer is created. The `organisations.slug` is computed by the application, and the wildcard DNS + nginx setup automatically makes `{slug}.rylees.ai` resolvable.

---

## 10. Module Service Provider

Create `app/Providers/ModuleServiceProvider.php` and register it in `bootstrap/providers.php`:

```php
class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Bind TranslationService
        $this->app->singleton(TranslationService::class);
    }
}
```

Each module's `routes.php` is loaded by `routes/api.php` as shown in section 4.

---

## 11. Testing Requirements

Framework: Pest 3 with `pest-plugin-laravel`. Use `RefreshDatabase` trait in all feature tests. Use `DatabaseTransactions` for faster test runs if preferred.

### Test classes and what each must cover

**`AuthTest`** (tests for AC-API-04, AC-API-05)
- `test_login_with_valid_credentials_returns_token`
- `test_login_with_inactive_user_returns_403_inactive_user`
- `test_login_with_wrong_password_returns_401_unauthenticated`
- `test_login_with_unknown_username_returns_401_unauthenticated` (same error, no user-not-found hint)
- `test_logout_revokes_token`

**`AccountTest`** (tests for AC-API-04)
- `test_register_creates_user_profile_and_organisation`
- `test_register_sets_is_active_false`
- `test_register_sends_activation_email`
- `test_activate_with_valid_token_activates_user`
- `test_activate_with_invalid_token_returns_404`
- `test_activate_with_already_used_token_returns_404`

**`DualAuthTest`** (tests for AC-API-05)
- `test_cli_auth_via_api_key_resolves_user` — send `Authorization: Bearer {api_key}` to a CLI endpoint; assert 200
- `test_web_auth_via_sanctum_token_resolves_user` — send `Authorization: Bearer {sanctum_token}`; assert 200
- `test_inactive_user_api_key_returns_403`

**`CustomerTest`** (tests for AC-API-06, AC-API-08)
- `test_list_customers_returns_paginated_response_with_projects_count`
- `test_create_customer_creates_organisation_and_customer`
- `test_create_customer_with_contact_sets_main_contact_id`
- `test_get_customer_returns_contacts_and_projects`
- `test_cannot_access_other_users_customer_returns_404`
- `test_patch_customer_does_not_accept_contacts_field`

**`ContactTest`** (tests for AC-API-08)
- `test_create_contact_returns_201`
- `test_delete_contact_sets_main_contact_id_to_null_if_it_was_main`
- `test_cannot_access_contact_of_other_users_customer`

**`ProjectTest`** (tests for AC-API-08, AC-API-09)
- `test_create_project_generates_key_and_token_and_creates_release_history`
- `test_project_token_appears_in_single_response_not_list`
- `test_patch_project_cannot_change_token_or_key`
- `test_project_detail_includes_customer_organisation_slug`

**`ReleaseHistoryTest`** (tests for AC-API-10)
- `test_publish_with_no_prior_notes_creates_version_0_1_0` (minor bump)
- `test_publish_major_bump_resets_minor_and_patch`
- `test_publish_minor_bump_resets_patch`
- `test_publish_commits_type_populates_commithash_columns`
- `test_publish_tag_type_populates_tag_columns`

**`PublicReleaseHistoryTest`** (tests for AC-API-11, AC-API-12)
- `test_public_endpoint_returns_notes_without_auth`
- `test_public_endpoint_returns_404_for_unknown_slug`
- `test_translate_with_invalid_language_returns_422`

**`SeedDataTest`** (tests for AC-API-13, AC-API-14)
- `test_ref_industries_returns_seeded_data`
- `test_ref_llm_tonalities_returns_4_records`
- `test_ref_llm_temperatures_returns_3_records`
- `test_seeder_is_idempotent` — run seed twice; assert no duplicate rows

---

## 12. Acceptance Criteria

### AC-API-01 — Framework and routing

- All routes are prefixed `/v1`.
- Every response carries `Content-Type: application/json`.
- All timestamps in responses are ISO 8601 UTC.
- All primary keys in responses are UUIDs.

### AC-API-02 — Database schema

- `php artisan migrate` runs without errors on a clean PostgreSQL 16 database.
- All tables except `*_types` lookup tables have `created_at`, `updated_at`, and `deleted_at` columns.
- Every foreign-key column carries the index specified in section 6.
- Soft-deleted records do not appear in any API response.

### AC-API-03 — Modular monolith structure

- Each module lives under `app/Modules/{ModuleName}/` with its own controllers, models, services, requests, resources, repositories, and `routes.php`.
- No module directly imports a Model from another module.

### AC-API-04 — Registration and email activation

- `POST /users/register` with valid data returns `201` and creates `organisations`, `users`, and `user_profiles` rows.
- The new user has `is_active = false` and a non-null `activation_token`.
- An activation email is sent containing a link with the `activation_token`.
- `GET /users/activate?token=<valid>` sets `is_active = true`, `activated_at`, and clears `activation_token`; returns `200`.
- `GET /users/activate?token=<invalid-or-used>` returns `404 not_found`.

### AC-API-05 — Authentication

- `POST /auth/login` with correct credentials and `is_active = true` returns `200` with a Bearer token and `expires_in: 3600`.
- `POST /auth/login` with `is_active = false` returns `403 inactive_user`.
- `POST /auth/login` with wrong credentials returns `401 unauthenticated`.
- `POST /auth/logout` revokes the current token.
- CLI requests via `Authorization: Bearer <api_key>` resolve the owning user correctly.

### AC-API-06 — Authorization scoping

- A developer cannot read, update, or delete customers or projects belonging to another developer; such requests return `404 not_found`.
- Public release history endpoints return data without any `Authorization` header.

### AC-API-07 — Standard error shape

- Every `4xx` response body matches `{ "message": "...", "code": "..." }`.
- Non-existent resources return `404`, not `500`.
- Invalid request payloads return `422 validation_error`.

### AC-API-08 — Customer and project management

- `POST /customers` creates an `organisations` row and a `customers` row; optionally creates a `customer_contacts` row and sets `main_contact_id`.
- `POST /customers/{customerId}/projects` creates a `projects` row with a generated `key` (slug, unique per customer) and a 64-character random `token`, and automatically creates one `release_histories` row.
- `PATCH` endpoints update only the fields provided; omitted fields remain unchanged.
- `projects.token` and `projects.key` cannot be changed via `PATCH /customers/{customerId}/projects/{id}`.

### AC-API-09 — Security field exposure

- `api_key` does not appear in any list-level response; it is present only in `GET /users/me`.
- `projects.token` does not appear in list responses (the global list via `GET /customers`); it is present in `GET /customers/{id}/projects`, `GET /customers/{id}/projects/{id}`, and the `POST` create response.
- Passwords are stored as bcrypt hashes; plaintext never appears in any response or log.

### AC-API-10 — CLI publish endpoint

- `POST /projects/{projectToken}/release-history` with `versionBump: "minor"` and no prior release notes creates a record with `version_major=0`, `version_minor=1`, `version_patch=0` and returns `201` with `{ "status": "published", "version": "0.1.0" }`.
- Subsequent bumps increment the correct component and reset lower components.
- `type = "commits"` populates `commithash_start` and `commithash_end`; `tag_start` and `tag_end` remain null, and vice versa.

### AC-API-11 — Public release history

- `GET /public/release-history/{customerSlug}/{projectKey}` returns release notes ordered newest first without authentication.
- Returns `404` when the slug/key combination does not resolve.

### AC-API-12 — Translation

- `GET /public/release-history/{customerSlug}/{projectKey}/translate?language=fr` calls the AI module and returns translated bodies.
- Missing or invalid `language` returns `422 validation_error`.

### AC-API-13 — Reference data

- `GET /ref/industries`, `GET /ref/llm-tonalities`, `GET /ref/llm-temperatures` return all seeded records without authentication.

### AC-API-14 — Seed data

- `php artisan db:seed` populates `llm_tonality_types` (4 records), `llm_temperature_types` (3 records), and `industry_types` (13 records) without errors and is idempotent on re-run.
