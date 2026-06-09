# Rylees API — Implementation Plan

## Context

The `src/api/` directory contains a stock Laravel 13 installation (no Sanctum, no Pest, no modules, no API routes file). The full specification lives in `src/api/spec/SPEC.md`. The goal is to build the complete Rylees Backend API — a modular monolith serving three client types (CLI, Developer Console, Public Release History) — from that spec.

**Rule:** Commit at the end of each phase once all tests (current + all previous phases) are green. Run `vendor/bin/pint` before every commit.

---

## Phase 1 — Foundation & Infrastructure

**Goal:** Bring the Laravel install in line with spec requirements and wire the skeleton that all modules will hang off.

### `composer.json` changes
- Bump `php` constraint `^8.3` → `^8.5`
- Add to `require`: `laravel/sanctum ^4.0`, `openai-php/client ^0.10`
- Add to `require-dev`: `pestphp/pest ^3.0`, `pestphp/pest-plugin-laravel ^3.0`, `laravel/pint`
- Remove from `require-dev`: `phpunit/phpunit` (replaced by Pest), `laravel/pail`, `laravel/pao` (not in spec)
- Run `composer update`

### Pest setup
- Create `pest.php` at project root — `uses(Tests\TestCase::class)->in('Feature')` with `RefreshDatabase`
- Convert existing example tests to Pest syntax

### `bootstrap/app.php` — full reconfiguration
```php
->withRouting(
    api: __DIR__.'/../routes/api.php',
    apiPrefix: '',
    health: '/up',
)
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(append: [
        \App\Http\Middleware\AuthenticateWithApiKey::class,
    ]);
    // no statefulApi() — stateless API
})
->withExceptions(function (Exceptions $exceptions) {
    // AuthenticationException  → 401 unauthenticated
    // AuthorizationException   → 403 forbidden
    // ModelNotFoundException   → 404 not_found
    // ValidationException      → 422 validation_error + errors
})
```

### CORS
Configure `config/cors.php`:
```php
'allowed_origins' => ['https://console.rylees.ai', 'https://*.rylees.ai', 'http://console.rylees.test', 'http://*.rylees.test'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => false,
```

### Files to create
| File | Purpose |
|------|---------|
| `routes/api.php` | v1 prefix; requires all module `routes.php` files |
| `app/Http/Middleware/AuthenticateWithApiKey.php` | Resolves `api_key` Bearer tokens (exact impl from SPEC §5.1) |
| `app/Http/Middleware/EnsureUserIsActive.php` | Checks `is_active`; returns 403 `inactive_user` |
| `app/Providers/ModuleServiceProvider.php` | Stub — TranslationService singleton added in Phase 7 |

Register `ModuleServiceProvider` in `bootstrap/providers.php`.

### Migrations to remove
Delete `2026_06_07_141912_create_sessions_table.php` — stateless API, sessions unused.

### Tests
- `tests/Feature/HealthTest.php` — `GET /up` → 200
- `tests/Feature/RoutingTest.php` — unknown route → JSON `{ message, code }`, not HTML

### Commit
`feat: configure Laravel 13 API foundation with Pest, Sanctum, and module routing`

---

## Phase 2 — Database: Migrations & Seed Data

**Goal:** All 11 migrations run clean on PostgreSQL; seeders populate lookup tables idempotently; reference endpoints return seeded data.

### Trait
- `app/Models/Concerns/HasSlug.php` — exact implementation from SPEC §6 (slug generation loop, uniqueness check, optional `slugScope` hook)

### Lookup models (in `app/Models/`, no timestamps/soft-deletes)
- `IndustryType.php`
- `LlmTonalityType.php`
- `LlmTemperatureType.php`

### Migrations (in dependency order, exact column definitions from SPEC §6)
1. `2026_01_01_000001_create_organisations_table.php`
2. `2026_01_01_000002_create_industry_types_table.php`
3. `2026_01_01_000003_create_llm_tonality_types_table.php`
4. `2026_01_01_000004_create_llm_temperature_types_table.php`
5. `2026_01_01_000005_create_users_table.php`
6. `2026_01_01_000006_create_user_profiles_table.php`
7. `2026_01_01_000007_create_customers_table.php` — `main_contact_id` nullable, no FK yet
8. `2026_01_01_000008_create_customer_contacts_table.php` — creates contacts table then adds FK on `customers.main_contact_id`
9. `2026_01_01_000009_create_projects_table.php` — composite unique `[customer_id, key]`
10. `2026_01_01_000010_create_release_histories_table.php`
11. `2026_01_01_000011_create_release_notes_table.php`

### Seeders
- `LlmTonalityTypeSeeder` — 4 records: neutral, professional, friendly, humorous
- `LlmTemperatureTypeSeeder` — 3 records: precise (0.2), balanced (0.5), creative (0.8)
- `IndustryTypeSeeder` — 13 records (Architecture → Other)
- `DatabaseSeeder` — calls all three; all use `updateOrInsert` (idempotent)

### Reference routes (no auth, closures in `app/Modules/ReleaseHistory/routes.php`)
- `GET /v1/ref/industries` → `{ items: [{ id, name }] }`
- `GET /v1/ref/llm-tonalities` → `{ items: [{ id, name }] }`
- `GET /v1/ref/llm-temperatures` → `{ items: [{ id, name, value }] }`

### Tests (AC-API-13, AC-API-14)
`tests/Feature/SeedDataTest.php`:
- `test_ref_industries_returns_seeded_data`
- `test_ref_llm_tonalities_returns_4_records`
- `test_ref_llm_temperatures_returns_3_records`
- `test_seeder_is_idempotent` — seed twice, assert counts unchanged

### Commit
`feat: add database migrations, HasSlug trait, lookup models, seeders, and ref endpoints`

---

## Phase 3 — Auth & Account Modules

**Goal:** User registration, email activation, login/logout, and account self-management all working and tested.

### Core models (in `app/Models/`)
- `User.php` — `HasApiTokens` (Sanctum), `SoftDeletes`; `$hidden = ['password', 'activation_token']`; has one UserProfile
- `UserProfile.php` — BelongsTo User, BelongsTo Organisation; `SoftDeletes`
- `Organisation.php` — uses `HasSlug`; HasMany UserProfile, HasMany Customer; `SoftDeletes`

### Auth module (`app/Modules/Auth/`)
| File | Purpose |
|------|---------|
| `routes.php` | `POST /auth/login`, `POST /auth/logout` (auth:sanctum) |
| `Requests/LoginRequest.php` | username (required\|email), password (required\|string) |
| `Services/AuthService.php` | `login(array): array`, `logout(User): void` |
| `Controllers/AuthController.php` | Delegates to AuthService; login returns token shape per SPEC §7.1 |
| `Mail/AccountActivationMail.php` | Mailable, markdown template, subject "Activate your Rylees account" |
| `resources/views/emails/account-activation.blade.php` | Greeting, explanation, activation button |

Activation link format: `https://console.rylees.ai/activate?token={activation_token}`

### Account module (`app/Modules/Account/`)
| File | Purpose |
|------|---------|
| `routes.php` | `POST /users/register` (201), `GET /users/activate`, then auth:sanctum+active group |
| `Requests/RegisterRequest.php` | username, password, profile.*, organisation.* |
| `Requests/UpdateAccountRequest.php` | All `sometimes`; current_password required_with:new_password |
| `Resources/UserResource.php` | Shapes `GET /users/me` — includes `api_key`, profile, organisation |
| `Services/AccountService.php` | `register()`, `activate()`, `updateMe()`, `destroyMe()` |
| `Repositories/AccountRepository.php` | DB queries for User/UserProfile/Organisation |
| `Controllers/AccountController.php` | Delegates to AccountService |

### Key invariants
- `activate()` throws `ModelNotFoundException` if token is invalid or null → global handler returns 404
- `destroyMe()` soft-deletes user + profile; does NOT delete Organisation
- `api_key` only in `GET /users/me` response (UserResource), never in register response

### Tests (AC-API-04, AC-API-05)

`tests/Feature/AuthTest.php`:
- `test_login_with_valid_credentials_returns_token`
- `test_login_with_inactive_user_returns_403_inactive_user`
- `test_login_with_wrong_password_returns_401_unauthenticated`
- `test_login_with_unknown_username_returns_401_unauthenticated`
- `test_logout_revokes_token`

`tests/Feature/AccountTest.php`:
- `test_register_creates_user_profile_and_organisation`
- `test_register_sets_is_active_false`
- `test_register_sends_activation_email`
- `test_activate_with_valid_token_activates_user`
- `test_activate_with_invalid_token_returns_404`
- `test_activate_with_already_used_token_returns_404`

`tests/Feature/DualAuthTest.php`:
- `test_cli_auth_via_api_key_resolves_user` — Bearer api_key on auth-required endpoint → 200
- `test_web_auth_via_sanctum_token_resolves_user` — Bearer sanctum_token → 200
- `test_inactive_user_api_key_returns_403`

### Commit
`feat: implement Auth and Account modules with registration, activation, and login`

---

## Phase 4 — Customer Module

**Goal:** Full customer and contact CRUD with per-user scoping and organisation slug generation.

### Files (`app/Modules/Customer/`)
| File | Purpose |
|------|---------|
| `Models/Customer.php` | BelongsTo User/Organisation/IndustryType/CustomerContact(main); HasMany CustomerContact, Project; SoftDeletes |
| `Models/CustomerContact.php` | BelongsTo Customer; SoftDeletes |
| `routes.php` | All routes under auth:sanctum+active |
| `Requests/ListCustomersRequest.php` | page, per_page (min:1, max:100) |
| `Requests/StoreCustomerRequest.php` | organisation.*, industry_id, description, main_contact.* |
| `Requests/UpdateCustomerRequest.php` | Same as store but `sometimes`; no contacts field |
| `Requests/StoreContactRequest.php` | firstname, lastname, email |
| `Requests/UpdateContactRequest.php` | All `sometimes` |
| `Resources/CustomerListResource.php` | Paginated list — no contacts array, includes projects_count |
| `Resources/CustomerDetailResource.php` | Show — contacts array, projects array (id/name/key only) |
| `Resources/ContactResource.php` | `{ id, firstname, lastname, email }` |
| `Services/CustomerService.php` | `store()`, `update()`, `storeContact()`, `updateContact()`, `destroyContact()` |
| `Repositories/CustomerRepository.php` | `paginatedForUser()`, `findForUser()` |
| `Repositories/ContactRepository.php` | Contact queries |
| `Controllers/CustomerController.php` | index (paginated), store (201), show, update |
| `Controllers/ContactController.php` | store (201), update, destroy (204) |

### Authorization pattern
- `$customer->user_id !== auth()->id()` → 404 `not_found` (never 403 — don't leak existence)
- Contact: also verify `$contact->customer_id === $customer->id`

### Key business rules
- `store()` creation order: Organisation → Customer → optional CustomerContact → set `main_contact_id`
- `destroyContact()`: soft-delete contact; if it was `main_contact_id`, null that field on Customer
- `PATCH /customers/{id}` never accepts contacts/main_contact — not in `UpdateCustomerRequest`

### Tests (AC-API-06, AC-API-08)

`tests/Feature/CustomerTest.php`:
- `test_list_customers_returns_paginated_response_with_projects_count`
- `test_create_customer_creates_organisation_and_customer`
- `test_create_customer_with_contact_sets_main_contact_id`
- `test_get_customer_returns_contacts_and_projects`
- `test_cannot_access_other_users_customer_returns_404`
- `test_patch_customer_does_not_accept_contacts_field`

`tests/Feature/ContactTest.php`:
- `test_create_contact_returns_201`
- `test_delete_contact_sets_main_contact_id_to_null_if_it_was_main`
- `test_cannot_access_contact_of_other_users_customer`

### Commit
`feat: implement Customer module with contact management and per-user scoping`

---

## Phase 5 — Project Module

**Goal:** Project CRUD nested under a customer; auto-generates key (slug) and token; creates a ReleaseHistory automatically.

### Files (`app/Modules/Project/`)
| File | Purpose |
|------|---------|
| `Models/Project.php` | Uses `HasSlug` — `slugColumn()` → `'key'`, `slugScope()` scoped to `customer_id`; `$guarded = ['token', 'key']`; SoftDeletes |
| `routes.php` | All routes under auth:sanctum+active, nested under `/customers/{customer}/projects` |
| `Requests/StoreProjectRequest.php` | name, description, llm_tonality_id, llm_temperature_id |
| `Requests/UpdateProjectRequest.php` | All `sometimes`; no token/key accepted |
| `Resources/ProjectListResource.php` | Includes token; omits customer block |
| `Resources/ProjectDetailResource.php` | Includes customer block with `organisation_slug`; `llm.temperature` (numeric value) + `llm.tonality` (name string) |
| `Services/ProjectService.php` | `store()`: HasSlug generates key, `token = Str::random(64)`, creates ReleaseHistory; `update()` |
| `Repositories/ProjectRepository.php` | Project queries |
| `Controllers/ProjectController.php` | index, store (201), show, update |

### Authorization pattern
- `$customer->user_id !== auth()->id()` → 404
- `$project->customer_id !== $customer->id` → 404

### Key invariants
- `token` and `key` set at creation, never change — enforced by `$guarded` and absent from `UpdateProjectRequest`
- Creating a project always creates exactly one `ReleaseHistory` row

### Tests (AC-API-08, AC-API-09)

`tests/Feature/ProjectTest.php`:
- `test_create_project_generates_key_and_token_and_creates_release_history`
- `test_project_token_appears_in_single_response_not_list`
- `test_patch_project_cannot_change_token_or_key`
- `test_project_detail_includes_customer_organisation_slug`

### Commit
`feat: implement Project module with auto-generated key, token, and release history creation`

---

## Phase 6 — ReleaseHistory Module

**Goal:** CLI publish endpoint with version computation; public release history read endpoint.

### Files (`app/Modules/ReleaseHistory/`)
| File | Purpose |
|------|---------|
| `Models/ReleaseHistory.php` | BelongsTo Project; HasMany ReleaseNote; SoftDeletes |
| `Models/ReleaseNote.php` | BelongsTo ReleaseHistory, BelongsTo User (author); SoftDeletes; no `type` column |
| `routes.php` | CLI route (auth:sanctum+active), public prefix (no auth), ref prefix (no auth) |
| `Requests/PublishReleaseNoteRequest.php` | startRef, endRef, type (in:commits,tag), branchName, body, versionBump (in:major,minor,patch) |
| `Resources/ReleaseNoteResource.php` | version string `"{major}.{minor}.{patch}"`, `publishedAt` = created_at ISO 8601 UTC |
| `Services/ReleaseHistoryService.php` | `publish()`: version computation + column population logic |
| `Repositories/ReleaseHistoryRepository.php` | Release note queries |
| `Controllers/ReleaseHistoryController.php` | `publish(Request, string $projectToken)` — find project by token, verify ownership |
| `Controllers/PublicReleaseHistoryController.php` | `index()` slug→org→customer→project→notes; `translate()` delegates to TranslationService |

### Version computation — implement verbatim from SPEC §7.5
```php
$latest = $history->releaseNotes()->whereNull('deleted_at')->orderByDesc('created_at')->first();
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

### Column population (type-conditional)
- `type === 'commits'` → populate `commithash_start`, `commithash_end`; tag columns null
- `type === 'tag'` → populate `tag_start`, `tag_end`; commithash columns null

### Tests (AC-API-10, AC-API-11, partial AC-API-12)

`tests/Feature/ReleaseHistoryTest.php`:
- `test_publish_with_no_prior_notes_creates_version_0_1_0`
- `test_publish_major_bump_resets_minor_and_patch`
- `test_publish_minor_bump_resets_patch`
- `test_publish_commits_type_populates_commithash_columns`
- `test_publish_tag_type_populates_tag_columns`

`tests/Feature/PublicReleaseHistoryTest.php`:
- `test_public_endpoint_returns_notes_without_auth`
- `test_public_endpoint_returns_404_for_unknown_slug`
- `test_translate_with_invalid_language_returns_422` (validation fails before AI is called)

### Commit
`feat: implement ReleaseHistory module with CLI publish and public read endpoints`

---

## Phase 7 — AI Module (Translation)

**Goal:** `TranslationService` wired to OpenAI; translate endpoint returns translated note bodies.

### Files
| File | Purpose |
|------|---------|
| `config/services.php` | Add `'openai' => ['key' => env('OPENAI_API_KEY')]` |
| `app/Modules/AI/Services/TranslationService.php` | Exact impl from SPEC §7.7 — OpenAI::client, model GPT-5.4, system prompt, json_decode response |
| `app/Modules/AI/routes.php` | Empty (no direct AI routes) |

Update `app/Providers/ModuleServiceProvider.php` — add `$this->app->singleton(TranslationService::class)`.

Update `PublicReleaseHistoryController` — inject `TranslationService` via constructor, call in `translate()`.

### Translate response shape
```json
{ "language": "fr", "items": [{ "id": "...", "version": "1.3.0", "body": "..." }] }
```
Note: `version` is reconstructed from the note's `version_major/minor/patch` columns, not from the AI response.

### Tests (AC-API-12 success path)
Add to `tests/Feature/PublicReleaseHistoryTest.php`:
- `test_translate_returns_translated_bodies` — mock `TranslationService`; assert it receives correct notes array and language; assert response shape

### Commit
`feat: implement AI translation module and wire translate endpoint`

---

## Verification (after Phase 7)

```bash
cd src/api
php artisan migrate:fresh --seed
vendor/bin/pest --coverage
```

---

## Phase 8 — Postmane Testsuite

After Phase 7 verification, prepare a [Postman](https://www.postman.com) test suite under `tests/Postman/Api`. Cover each API endpoint and prepare a `test` and a `production` environment. Use variables like base_url and api_token per environment. Fill in meaningful payload in requests.

---

All tests must pass. Spot-check acceptance criteria manually:
- `GET /up` → 200
- `POST /v1/users/register` → 201, activation email queued
- `GET /v1/users/activate?token=...` → 200
- `POST /v1/auth/login` → 200 with Bearer token + expires_in: 3600
- `GET /v1/users/me` → includes `api_key`
- `POST /v1/projects/{token}/release-history` with Bearer api_key → 201 `{ status: "published", version: "0.1.0" }`
- `GET /v1/public/release-history/{slug}/{key}` → 200, no auth header required
- `GET /v1/ref/industries` → 13 items

---

## Cross-cutting rules (every phase)

- All responses are `JsonResponse` — never naked arrays
- Every `4xx` must match `{ message, code }` — enforced by global exception handler from Phase 1
- Modules must never import a Model from another module — use service class method calls only
- `api_key` appears only in `GET /users/me`
- `projects.token` appears only in detail/create responses, never in list-level collections
- Run `vendor/bin/pint` before every commit
- No raw SQL — Eloquent or query builder only
