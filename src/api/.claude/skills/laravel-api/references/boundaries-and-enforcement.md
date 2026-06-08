# Boundaries & Enforcement

Conventions decay unless a machine checks them. Wire both of these into CI so a boundary violation fails the build, not a code review that someone might skim. Pest arch tests are fast and live with your suite; Deptrac is the rigorous backstop with a dependency graph.

## The rules being enforced

1. **Layer direction inside a module:** `Presentation → Application → Domain`, and `Infrastructure → Domain`. `Domain` depends on nothing outward — not Laravel, not another module.
2. **Module isolation:** a module may reference another module *only* through that module's `Public\` namespace (contracts, DTOs, events). Never `Domain`, `Application`, or `Infrastructure` of another module.
3. **No cycles:** module dependencies form a DAG. If A uses B, B must not use A — break it with an event or redraw the boundary.
4. **DTOs are immutable:** classes under `Public\Data` are `readonly`.

When a needed dependency is disallowed, that's a design signal, not a reason to add an exception. Route it through a public contract or an event, or the boundary is wrong.

## Pest architecture tests

`arch()` expectations read as plain assertions and run with `php artisan test`. Keep them in `tests/Architecture/`.

```php
// Domain purity — no framework, no sibling modules
arch('domain layers are pure')
    ->expect('Modules\*\Domain')
    ->not->toUse(['Illuminate', 'Modules\*\Infrastructure', 'Modules\*\Presentation']);

// Application must not reach into Infrastructure (depend on ports, not adapters)
arch('application depends on domain, not infrastructure')
    ->expect('Modules\*\Application')
    ->not->toUse('Modules\*\Infrastructure');

// Cross-module access is restricted to Public — assert per ordered pair
arch('project touches only customer public')
    ->expect('Modules\Project')
    ->not->toUse([
        'Modules\Customer\Domain',
        'Modules\Customer\Application',
        'Modules\Customer\Infrastructure',
        'Modules\Customer\Presentation',
    ]);

arch('release history touches only project public')
    ->expect('Modules\ReleaseHistory')
    ->not->toUse([
        'Modules\Project\Domain',
        'Modules\Project\Application',
        'Modules\Project\Infrastructure',
        'Modules\Project\Presentation',
    ]);

// DTOs crossing boundaries are immutable
arch('public DTOs are readonly')
    ->expect('Modules\*\Public\Data')
    ->toBeReadonly();

// Controllers stay thin: no direct DB/query-builder use
arch('controllers do not query the database')
    ->expect('Modules\*\Presentation\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB']);
```

Pest's preset helpers (`->toBeReadonly()`, `->toBeFinal()`, `->toBeInvokable()`) are useful for the conventions in the main skill — e.g. assert actions are `final` and invokable, enums back your status fields, etc.

## Deptrac

Deptrac builds a real dependency graph and fails on any edge you didn't allow. Define each module and each layer as a layer, then declare the permitted ruleset. Sketch of `deptrac.yaml`:

```yaml
deptrac:
  paths: [src/Modules]
  layers:
    # One layer per module
    - name: Customer
      collectors: [{ type: directory, value: src/Modules/Customer/.* }]
    - name: Project
      collectors: [{ type: directory, value: src/Modules/Project/.* }]
    - name: ReleaseHistory
      collectors: [{ type: directory, value: src/Modules/ReleaseHistory/.* }]
    - name: AI
      collectors: [{ type: directory, value: src/Modules/AI/.* }]
    - name: Auth
      collectors: [{ type: directory, value: src/Modules/Auth/.* }]
    - name: Account
      collectors: [{ type: directory, value: src/Modules/Account/.* }]
    - name: Shared
      collectors: [{ type: directory, value: src/Modules/Shared/.* }]

    # Public surfaces, so "use only the door" is expressible
    - name: CustomerPublic
      collectors: [{ type: directory, value: src/Modules/Customer/Public/.* }]
    - name: ProjectPublic
      collectors: [{ type: directory, value: src/Modules/Project/Public/.* }]

  ruleset:
    Project:
      - CustomerPublic    # Project may use Customer's Public surface...
      - Shared            # ...and the shared kernel — nothing else
    ReleaseHistory:
      - ProjectPublic     # ReleaseHistory may use Project's Public surface...
      - Shared
    AI:
      - Shared
    Customer:
      - Shared
    Auth:
      - Shared
    Account:
      - Shared
    Shared: ~             # Shared depends on no module
```

Run `vendor/bin/deptrac analyse` in CI. Because `Project` is allowed `CustomerPublic` but not `Customer`, any import of Customer's internals is reported as an uncovered dependency and fails the build. The same technique enforces intra-module layer direction by declaring `Domain`/`Application`/`Infrastructure`/`Presentation` as layers with a one-directional ruleset.

## CI wiring

Add both to the pipeline so neither can be skipped:

```yaml
- run: php artisan test --filter=Architecture
- run: vendor/bin/deptrac analyse --fail-on-uncovered --no-progress
```

## Reading violations as design feedback

- **"Project uses Customer\Infrastructure"** → you're reaching past the contract; add the needed method to `CustomerApi` (or an event) and depend on that.
- **"A→B and B→A"** → a cycle; usually one direction should be an event instead of a call, or the two share a responsibility that belongs in a third module or Shared.
- **"Domain uses Illuminate"** → leaking framework into the core; move the framework concern out to Infrastructure/Presentation behind a port.

Fixing the design is the job. Suppressing the rule just moves the rot somewhere a tool can't see it.
