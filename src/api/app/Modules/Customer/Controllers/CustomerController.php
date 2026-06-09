<?php

declare(strict_types=1);

namespace App\Modules\Customer\Controllers;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Repositories\CustomerRepository;
use App\Modules\Customer\Requests\ListCustomersRequest;
use App\Modules\Customer\Requests\StoreCustomerRequest;
use App\Modules\Customer\Requests\UpdateCustomerRequest;
use App\Modules\Customer\Resources\CustomerDetailResource;
use App\Modules\Customer\Resources\CustomerListResource;
use App\Modules\Customer\Services\CustomerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerController
{
    public function __construct(
        private readonly CustomerService $service,
        private readonly CustomerRepository $repository,
    ) {}

    public function index(ListCustomersRequest $request): JsonResponse
    {
        $page = (int) $request->integer('page', 1);
        $perPage = (int) $request->integer('per_page', 15);

        $paginator = $this->repository->paginatedForUser($request->user(), $page, $perPage);

        return response()->json([
            'data' => CustomerListResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated(), (string) $request->user()->id);

        return response()->json($result, 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomer($customer);

        $customer->load(['organisation', 'mainContact', 'industry', 'contacts', 'projects']);

        return response()->json((new CustomerDetailResource($customer))->resolve());
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomer($customer);

        $result = $this->service->update($customer, $request->validated());

        return response()->json($result);
    }

    private function authorizeCustomer(Customer $customer): void
    {
        if ($customer->user_id !== auth()->id())
        {
            throw new ModelNotFoundException;
        }
    }
}
