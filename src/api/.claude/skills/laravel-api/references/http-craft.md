# HTTP Craft Inside a Module

How to build endpoints within a module's `Presentation/Http` layer. The architecture rules (slicing, boundaries, contracts) come from the main skill; this is the request-level standard that applies *inside* any module. The one architectural constraint: a controller calls its **own** module's `Application` layer, and reaches other modules only through their `Public` contracts.

## The request path within a module

```
Route (module) → Middleware → Form Request → thin Controller → Application action → Domain/Infra → API Resource → JSON
```

Each layer has one job. Skipping layers produces untestable controllers and responses coupled to the database schema.

## Routing & versioning

Routes live in the module (`Presentation/routes.php`), loaded by the module's service provider. Version from day one and namespace by module: `/api/v1/billing/...`. Use `Route::apiResource()` for CRUD and route–model binding so missing records 404 automatically. An invokable single-action controller fits non-REST actions (`POST .../invoices/{invoice}/pay`).

## Thin controllers

A controller validates, delegates, and responds — nothing more.

```php
public function publish(PublishReleaseNoteRequest $request, string $projectToken, PublishReleaseNote $publish): ReleaseNoteResource
{
    return new ReleaseNoteResource($publish($projectToken, $request->validated(), auth()->id()));
}
```

Branching business rules, transactions, and orchestration belong in an `Application` action, not the controller.

## Validation with Form Requests

One request class per write op (`PayInvoiceRequest`, `IssueInvoiceRequest`). `rules()` for rules, `authorize()` for the gate, `prepareForValidation()` to normalize input. Always pass `$request->validated()` (never `all()`) into the action — only whitelisted keys reach a model, which is the main guard against mass-assignment. Use `Rule::enum(...)`, scoped `Rule::exists`/`unique`.

## API Resources — shape every response

Never return an Eloquent model or a cross-module DTO raw; always pass through a `JsonResource` so the public JSON contract is decoupled from internal shapes.

```php
final class ReleaseNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'version'     => "{$this->version_major}.{$this->version_minor}.{$this->version_patch}",
            'body'        => $this->body,
            'publishedAt' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
```

Use `whenLoaded()` for relations (avoids N+1 and conditional inclusion), `when()` for fields gated by role, and return paginated collections so `meta`/`links` are added automatically.

## Status codes & errors

Pick one envelope (`{ "data": ... }` is the sensible default) and never return `200` with an error body. Map outcomes to status: `200` read/update, `201` created, `204` no body, `401` unauthenticated, `403` forbidden, `404` not found, `422` validation, `429` rate-limited, `500` unhandled.

Centralize JSON error rendering in `bootstrap/app.php` `->withExceptions(...)`. Map domain exceptions (e.g. `InvoiceNotFound`) to clean client errors with the right status, mirror the `{ message, errors }` shape Laravel uses for `422`, and keep `APP_DEBUG=false` in production so traces never leak.

## Pagination & performance

Always paginate index endpoints and clamp `per_page` to a max. Eager-load relations the response serializes (`with(...)`) and pair with `whenLoaded` so loading and serialization stay in sync; enable `Model::preventLazyLoading()` in dev to surface N+1s. Whitelist filter/sort columns — never feed request input into `orderBy`/`where` column names. Reach for `spatie/laravel-query-builder` rather than hand-parsing query strings.

## Auth & rate limiting

This project uses dual-mode Bearer token auth (Sanctum personal access token for web clients, permanent `api_key` for the CLI). The full wiring — `AuthenticateWithApiKey` middleware, token issuance, inactive-user check, centralized error shapes, and security field-exposure rules — is in `references/auth-patterns.md`. Read it before touching auth or middleware code.

Authorize through Policies (`$this->authorize('pay', $invoice)`), not scattered `if` checks. Scope queries to `auth()->id()` and return `404` (not `403`) when a record does not belong to the caller — this avoids leaking resource existence. Apply named rate limiters via `throttle:` middleware, keyed by user when possible and IP otherwise; throttle auth endpoints aggressively.

## Testing the endpoint

Pest feature tests hit the real route with factories and `RefreshDatabase`. Per endpoint cover: happy path, `422` validation, `401` unauthenticated, `403` forbidden, `404` not found. Assert with `assertJsonPath`/`assertJsonStructure` (not brittle full-body matches). When the endpoint depends on another module, fake that module's `Public` contract so the test doesn't couple to its implementation.
