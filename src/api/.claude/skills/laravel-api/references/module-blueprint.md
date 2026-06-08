# Module Blueprint: a bounded context done right

A complete `Customer` module, layer by layer, showing how the public surface, domain core, application layer, infrastructure adapters, and HTTP delivery fit together — and how another module (`Project`) consumes it without touching its internals. Laravel 13 / PHP 8.5. Copy the structure, not the domain.

## Folder tree

```
src/Modules/Customer/
├── Public/
│   ├── CustomerApi.php
│   ├── Data/CustomerData.php
│   └── Events/CustomerCreated.php
├── Domain/
│   ├── Customer.php
│   ├── CustomerContact.php
│   └── CustomerRepository.php
├── Application/
│   ├── CustomerApiService.php
│   └── CreateCustomer.php
├── Infrastructure/
│   └── EloquentCustomerRepository.php
├── Presentation/
│   ├── Http/Controllers/CustomerController.php
│   ├── Http/Requests/StoreCustomerRequest.php
│   ├── Http/Resources/CustomerResource.php
│   └── routes.php
├── Database/Migrations/xxxx_create_customers_table.php
└── CustomerServiceProvider.php
```

## 1. Public surface — the only door

```php
namespace Modules\Customer\Public;

use Modules\Customer\Public\Data\CustomerData;

/** The complete contract other modules may depend on. */
interface CustomerApi
{
    public function find(string $customerId): ?CustomerData;

    public function existsForUser(string $customerId, string $userId): bool;
}
```

```php
namespace Modules\Customer\Public\Data;

/** readonly DTO — safe to hand to another module; PHP 8.5 clone() for updates. */
final readonly class CustomerData
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $organisationName,
        public string $organisationSlug,
        public ?string $industryName,
    ) {}

    public static function from(\Modules\Customer\Domain\Customer $customer): self
    {
        return new self(
            id: $customer->id,
            userId: $customer->user_id,
            organisationName: $customer->organisation->name,
            organisationSlug: $customer->organisation->slug,
            industryName: $customer->industry?->name,
        );
    }
}
```

```php
namespace Modules\Customer\Public\Events;

use Modules\Customer\Public\Data\CustomerData;

final readonly class CustomerCreated
{
    public function __construct(public CustomerData $customer) {}
}
```

## 2. Domain — pure core, no framework reach across the boundary

```php
namespace Modules\Customer\Domain;

/** Repository PORT. Infrastructure implements it; the domain depends only on this. */
interface CustomerRepository
{
    public function find(string $id): ?Customer;
    public function save(Customer $customer): void;
    public function existsForUser(string $id, string $userId): bool;
}
```

The `Customer` and `CustomerContact` aggregates may be Eloquent models (pragmatic in Laravel) — what matters is that they never leave the module as models; they leave as `CustomerData`.

## 3. Application — use cases

```php
namespace Modules\Customer\Application;

use Modules\Customer\Domain\{Customer, CustomerRepository};
use Modules\Customer\Public\Events\CustomerCreated;
use Modules\Customer\Public\Data\CustomerData;

final readonly class CreateCustomer
{
    public function __construct(private CustomerRepository $customers) {}

    public function __invoke(array $data, string $userId): CustomerData
    {
        $customer = Customer::make([
            'user_id' => $userId,
            // ... map remaining fields ...
        ]);
        $this->customers->save($customer);

        $dto = CustomerData::from($customer);
        CustomerCreated::dispatch($dto);  // others react; Customer stays unaware of them

        return $dto;
    }
}
```

```php
namespace Modules\Customer\Application;

use Modules\Customer\Public\CustomerApi;
use Modules\Customer\Public\Data\CustomerData;
use Modules\Customer\Domain\CustomerRepository;

/** Implements the public contract; bound in the service provider. */
final readonly class CustomerApiService implements CustomerApi
{
    public function __construct(private CustomerRepository $customers) {}

    public function find(string $customerId): ?CustomerData
    {
        $customer = $this->customers->find($customerId);
        return $customer ? CustomerData::from($customer) : null;
    }

    public function existsForUser(string $customerId, string $userId): bool
    {
        return $this->customers->existsForUser($customerId, $userId);
    }
}
```

## 4. Infrastructure — the adapter implementing the port

```php
namespace Modules\Customer\Infrastructure;

use Modules\Customer\Domain\{Customer, CustomerRepository};

final class EloquentCustomerRepository implements CustomerRepository
{
    public function find(string $id): ?Customer
    {
        return Customer::with(['organisation', 'industry', 'mainContact'])->find($id);
    }

    public function save(Customer $customer): void { $customer->save(); }

    public function existsForUser(string $id, string $userId): bool
    {
        return Customer::where('id', $id)->where('user_id', $userId)->exists();
    }
}
```

## 5. Presentation — HTTP delivery (see references/http-craft.md for the full standard)

```php
namespace Modules\Customer\Presentation\Http\Controllers;

use Modules\Customer\Application\CreateCustomer;

final class CustomerController
{
    public function store(StoreCustomerRequest $request, CreateCustomer $createCustomer): CustomerResource
    {
        // delegates to its OWN module's application layer
        $data = $createCustomer($request->validated(), auth()->id());
        return new CustomerResource($data);
    }
}
```

```php
// src/Modules/Customer/Presentation/routes.php
Route::middleware(['auth:sanctum', 'active'])
    ->prefix('v1')
    ->group(function () {
        Route::get('customers', [CustomerController::class, 'index']);
        Route::post('customers', [CustomerController::class, 'store']);
        Route::get('customers/{customer}', [CustomerController::class, 'show']);
        Route::patch('customers/{customer}', [CustomerController::class, 'update']);
    });
```

## 6. Service provider — the module self-registers

```php
namespace Modules\Customer;

final class CustomerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerApi::class, CustomerApiService::class);
        $this->app->bind(CustomerRepository::class, EloquentCustomerRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Presentation/routes.php');
    }
}
```

Register it once in `bootstrap/providers.php`.

## 7. How another module consumes Customer — through the door only

```php
namespace Modules\Project\Application;

use Modules\Customer\Public\CustomerApi;   // contract — allowed
use Modules\Customer\Public\Data\CustomerData; // DTO — allowed
// NOTHING from Modules\Customer\Domain|Application|Infrastructure — forbidden

final readonly class CreateProject
{
    public function __construct(private CustomerApi $customers) {}

    public function __invoke(CreateProjectData $data): ProjectData
    {
        // Verify the customer belongs to the authenticated user
        $customer = $this->customers->find($data->customerId)
            ?? throw new CustomerNotFound($data->customerId);

        if ($customer->userId !== $data->userId) {
            throw new CustomerNotFound($data->customerId); // 404, not 403
        }

        $project = Project::make([
            'customer_id' => $customer->id, // store only the id — no cross-module relation
            // ...
        ]);
        $project->save();

        return ProjectData::from($project);
    }
}
```

For read models: if `ReleaseHistory` needs to show customer organisation slug on the public history page, it keeps a local denormalized copy updated by subscribing to `CustomerCreated` (and a hypothetical `CustomerUpdated`) — it never queries `customers` or `organisations` directly.

## 8. Architecture test that protects all of this

```php
// tests/Architecture/CustomerBoundaryTest.php  (Pest)
arch('customer domain stays pure')
    ->expect('Modules\Customer\Domain')
    ->not->toUse(['Illuminate', 'Modules\Project', 'Modules\ReleaseHistory']);

arch('project uses only Customer\Public')
    ->expect('Modules\Project')
    ->not->toUse([
        'Modules\Customer\Domain',
        'Modules\Customer\Application',
        'Modules\Customer\Infrastructure',
    ]);
```

This is the shape every module takes. The payoff: any module can be understood, tested, and — if it ever must be — extracted, on its own.
