---
name: laravel-modular-monolith
description: Design, build, and review Laravel 13 / PHP 8.5 applications structured as a modular monolith — one deployable app internally split into bounded-context modules with explicit public APIs and enforced boundaries. Use this whenever the work touches how a Laravel codebase is organized — deciding where a feature or file belongs, creating or splitting modules, defining module boundaries and contracts, cross-module communication (service contracts, domain events), per-module service providers/routes/migrations, dependency direction, or enforcing architecture with Deptrac or Pest arch tests. Trigger it even when the user just says "where should this live", "add a new module", "this is getting tangled", "split the domain", "expose X to the Y module", or "set up the project structure" — and use it in preference to ad-hoc generation for any structural decision in a Laravel app.
---

# Laravel Modular Monolith

A modular monolith is one deployable application whose interior is divided into **modules**, each owning a **bounded context** — a cohesive slice of the business (Customer, Project, ReleaseHistory, Auth). Each module is as self-contained as a small service would be: its own domain logic, persistence, HTTP surface, and a narrow **public API**. But they all run in one process, share one deploy, and can participate in one database transaction.

The payoff — and the reason to accept the discipline — is **optionality with simplicity**. You get microservice-style boundaries (clear ownership, low coupling, parallelizable teams) without the distributed-systems tax (network calls, eventual consistency everywhere, deployment choreography). Refactoring stays a local operation. And when a module genuinely needs independent scaling or deployment, a clean boundary makes extracting it into a real service tractable instead of a year-long archaeology project. Build for that future without paying for it now.

The failure mode this guards against is the **big ball of mud**: a Laravel app where `app/Models` has 200 classes, every model relates to every other, and a change to invoicing breaks the catalog. Technical-layer folders (`Controllers/`, `Models/`, `Services/`) scale terribly because they put things that *change together* far apart and things that *change independently* together.

Target stack: **Laravel 13, PHP 8.5**. Use modern idioms — typed everything, enums, `readonly` value objects/DTOs (with PHP 8.5 `clone()` property overrides for "wither" updates), property hooks, first-class callables.

> Four reference files carry the detail so this spine stays scannable:
> - `references/module-blueprint.md` — a complete module (Customer) wired end to end. Read it before creating a module.
> - `references/boundaries-and-enforcement.md` — Deptrac config + Pest `arch()` tests that make the rules real. Read it when setting up or fixing boundary enforcement.
> - `references/http-craft.md` — what good looks like *inside* a module's HTTP layer (validation, resources, status codes, errors). Read it when building endpoints within a module.
> - `references/auth-patterns.md` — the dual-mode auth system (Sanctum token + permanent `api_key`), `AuthenticateWithApiKey` middleware, inactive-user check, centralized error shapes, and security field-exposure rules. Read it before touching any auth, middleware, or error-handling code.

## Slice by capability, not by layer

The first and most consequential decision is how to cut the system into modules. Cut along **business capabilities** (bounded contexts), each a noun a domain expert would recognize: `Auth`, `Account`, `Customer`, `Project`, `ReleaseHistory`, `AI`. A good module has high internal cohesion (its files change together for the same reasons) and low external coupling (it rarely needs to reach into others).

The modules in this project: `Auth`, `Account`, `Customer`, `Project`, `ReleaseHistory`, `AI`. Each owns a clear capability — `Customer` owns the customer/contact/organisation data, `Project` owns the project lifecycle, `ReleaseHistory` owns publishing and public read history, `AI` owns translation. `Auth` and `Account` are split because login/token issuance and registration/profile management change for different reasons.

Heuristics when you're unsure where a boundary sits: follow the language (if "project" means different things to the CLI publisher and the console user, look closely); follow the data ownership (who is the source of truth for this entity?); and watch the seams that change independently. When two "modules" always change together, they're one module. When one module has a god-like reach into everything, it's hiding several.

Resist slicing by technical role. `Validation`, `Repositories`, `DTOs` are not modules — they are layers that exist *inside every* module.

## Top-level layout

Give modules a dedicated namespace root so they read as peers of the framework, not as part of `App\`. A single PSR-4 entry keeps autoloading zero-ceremony:

```jsonc
// composer.json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Modules\\": "src/Modules/"
    }
}
```

```
src/Modules/
├── Shared/            # tiny shared kernel (see below)
├── Auth/
├── Account/
├── Customer/
├── Project/
├── ReleaseHistory/
└── AI/
```

For teams that want composer to *enforce* boundaries at the dependency-manager level, promote each module to a local path-repository package with its own `composer.json` — then a module literally cannot use code it doesn't `require`. That's heavier; reach for it only once a single PSR-4 root plus arch tests stops being enough.

## Anatomy of a module

Inside a module, organize by a light hexagonal scheme. The point of the layers is **dependency direction**: the domain knows nothing about Laravel, HTTP, or the database; outer layers depend inward, never the reverse.

```
src/Modules/Customer/
├── Public/                 # the ONLY thing other modules may touch
│   ├── CustomerApi.php         # contract (interface) other modules call
│   ├── Data/CustomerData.php   # readonly DTOs crossing the boundary
│   └── Events/CustomerCreated.php  # domain events other modules subscribe to
├── Domain/                 # pure business core — no framework imports
│   ├── Customer.php            # entity / aggregate (may be an Eloquent model)
│   ├── CustomerContact.php     # entity
│   └── CustomerRepository.php  # repository INTERFACE (port)
├── Application/            # use cases orchestrating the domain
│   └── CreateCustomer.php      # an invokable action / command handler
├── Infrastructure/        # adapters: persistence, external clients
│   └── EloquentCustomerRepository.php
├── Presentation/          # delivery: HTTP, console, queue entry points
│   ├── Http/Controllers/...
│   ├── Http/Requests/...
│   ├── Http/Resources/...
│   └── routes.php
├── Database/
│   └── Migrations/, Factories/
└── CustomerServiceProvider.php
```

Dependency direction, inviolable: `Presentation → Application → Domain` and `Infrastructure → Domain` (infrastructure implements domain ports). `Domain` imports nothing outward — not Laravel, not another module. This is what keeps the core testable and portable, and it is exactly what the arch tests in `references/boundaries-and-enforcement.md` verify.

## A module's public API is its only door

Every module publishes a small, deliberate surface under `Public/` and marks everything else `@internal`. Other modules may depend on:

1. **Contracts** — interfaces describing what the module can do (`BillingApi`), bound to implementations in the module's service provider.
2. **DTOs** — `readonly` data objects that carry data across the boundary. Never pass an Eloquent model across a module line; it drags the whole persistence layer and lets callers mutate another context's state.
3. **Events** — domain events the module emits, which others may listen to.

Everything under `Domain/`, `Application/`, `Infrastructure/`, `Presentation/` is private. A caller in `Project` reaching into `Modules\Customer\Infrastructure\EloquentCustomerRepository` is the cardinal sin — it couples Project to Customer's database choices and silently breaks the moment Customer refactors. The boundary is the contract, nothing else.

## Cross-module communication

Two sanctioned mechanisms, chosen by coupling needs:

**Synchronous — call a published contract.** When module A needs an answer or an action from module B *now*, A depends on B's `Public` interface, resolved from the container. A never news-up B's classes.

```php
// In Project — depends on Customer's contract, not its internals
final readonly class CreateProject
{
    public function __construct(private CustomerApi $customers) {}

    public function __invoke(CreateProjectData $data): void
    {
        $customer = $this->customers->find($data->customerId)
            ?? throw new CustomerNotFound($data->customerId);
        // ... create the project, storing only customer->id as a soft reference ...
    }
}
```

**Asynchronous — raise a domain event.** When the originating module shouldn't know or care who reacts (side effects: update a read model, notify), emit an event and let other modules subscribe. This is the looser, preferred coupling for anything that isn't a direct request/response.

```php
// ReleaseHistory emits — and has no idea AI or Notifications are listening
ReleaseNotePublished::dispatch(ReleaseNoteData::from($note));

// Account subscribes to notify the project owner, in its own service provider
Event::listen(ReleaseNotePublished::class, NotifyProjectOwner::class);
```

Prefer events for decoupling; reserve synchronous contract calls for when you truly need the result inline. Either way, what crosses the wire is a DTO or an event payload — never a live model.

## Each module owns its data

A module is the sole writer and source of truth for its tables. This is the boundary that matters most for future extraction.

- Prefix tables by context (`customer_contacts`, `release_notes`, `project_tokens`) or use separate schemas, so ownership is legible.
- **No Eloquent relationships or joins across module boundaries.** `Project` does not `belongsTo` a `Customer\Customer`. Cross-context references are stored as plain IDs (`customer_id`), and you fetch the other side through that module's public API — not a relation.
- **No foreign-key constraints across modules.** A hard FK welds two schemas together and blocks extraction. Treat cross-module references as soft references; enforce integrity through the owning module's API and events.
- When a module needs a *view* of another's data for queries (e.g. `ReleaseHistory` wants to show customer organisation slug on the public history page), build a local **read model** updated via the other module's events, rather than querying its tables directly.

Yes, this gives up some database-level convenience. That's the deliberate price of boundaries that hold.

## Wiring modules into Laravel

Each module self-registers everything through its own service provider — the framework should need no global knowledge of a module's guts.

```php
final class CustomerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the public contract to its private implementation
        $this->app->bind(CustomerApi::class, CustomerApiService::class);
        $this->app->bind(CustomerRepository::class, EloquentCustomerRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Presentation/routes.php');
        $this->registerEvents();
    }
}
```

Register the module providers in one place — `bootstrap/providers.php` (Laravel 11+/13) — or auto-discover them by globbing `src/Modules/*/*ServiceProvider.php` in a single `ModulesServiceProvider`. Explicit listing is easier to reason about; auto-discovery is convenient at scale. Routes get registered per module and still follow API versioning (`/api/v1/billing/...`); migrations, config, factories, and listeners all live with their module.

## The Shared kernel — keep it anemic

A `Support` module holds only what is genuinely universal and stable: a UUID wrapper, a base readonly DTO/event class, common exceptions, framework glue. It is a coupling magnet — everything depends on it, so anything that lives there is effectively impossible to change. The rule: if you're unsure whether something belongs in Shared, it doesn't. Duplication across two modules is cheaper than a wrong abstraction welding them together.

## Make the boundaries real — enforce them

Conventions that aren't enforced decay; a modular monolith without mechanical enforcement reliably rots back into a ball of mud, just with extra folders. Wire enforcement into CI from day one. Two complementary tools, both detailed in `references/boundaries-and-enforcement.md`:

- **Pest architecture tests** (`arch()`) — assert in plain PHP that `Domain` doesn't import Laravel, that modules only touch each other's `Public` namespace, that `Application` doesn't depend on `Infrastructure`, that DTOs are `readonly`, and so on. They run with your normal test suite.
- **Deptrac** — declares each module and layer as a boundary and fails the build on any disallowed dependency, including circular ones. This is the backstop that catches what a code reviewer misses.

If a needed dependency is disallowed, that's a design signal — either it should go through a public contract/event, or the boundary is drawn wrong. Don't whitelist your way around it reflexively.

## Inside a module: the request path

Within a module's `Presentation/Http`, build endpoints to the same standard a focused API deserves: thin controllers that validate via Form Requests, delegate to an `Application` action, and return through an API Resource — never a raw model. The full craft (validation, resource shaping, status codes, centralized JSON errors, pagination, Sanctum auth + policies, rate limiting) lives in `references/http-craft.md`. The one architectural addition here: a controller calls *its own* module's application layer, and reaches other modules only through their public contracts.

## Testing

- **Unit-test the domain** in isolation — it has no framework dependencies, so these tests are fast and stable.
- **Feature-test each module's endpoints** through the real HTTP routes with Pest, factories, and `RefreshDatabase`.
- **Architecture-test the boundaries** as described above — treat a boundary violation as a failing test, not a style nit.
- Test a module against other modules' **contracts** (fake the interface), so a module's tests don't depend on another module's implementation.

## Conventions

- PHP 8.5 / Laravel 13: typed signatures everywhere, `enum`s for fixed sets, `final` by default, `readonly` DTOs and value objects, property hooks where they earn their place.
- ULID/UUID public identifiers so IDs can cross module boundaries without leaking sequence/count and so extraction doesn't require ID remapping.
- PSR-12, consistent `Modules\<Context>\<Layer>\...` namespacing, named routes per module.
- One bounded context per module, one aggregate root per write transaction.

## Anti-patterns (treat as review blockers)

- A god `Shared` module accumulating business logic. Shared is for stable primitives only.
- Eloquent relationships, joins, or FK constraints across module boundaries (e.g. `Project belongsTo Customer\Customer`). Reference by ID; cross via API/events.
- Importing another module's `Domain`/`Infrastructure`/`Application` — anything outside its `Public` (e.g. `ReleaseHistory` directly querying `projects` or `customers` tables).
- Passing Eloquent models across boundaries instead of DTOs (e.g. returning a raw `Customer` model from `CustomerApi`).
- Circular dependencies between modules (A→B→A). Break with an event or by re-drawing the boundary.
- Business logic in controllers or fat models; it belongs in the Application/Domain layers.
- "Modules" named after technical layers (`Services`, `Repositories`) rather than capabilities.

## The extraction path

The whole structure is justified by what it enables later. Because a module already owns its data, exposes only a contract, and communicates via DTOs and events, extracting it to a standalone service becomes a contained job: replace the in-process contract binding with an HTTP/queue client implementing the same interface, point its tables at their own database, and swap synchronous calls for the event-driven equivalents you've largely already built. For example, if `AI` translation needs to scale independently, replacing `TranslationService` behind its contract with a queue-backed client becomes a localised change. You don't have to do this — most modular monoliths happily never split — but the option is real, and it's free as long as the boundaries hold. That is the entire point.
