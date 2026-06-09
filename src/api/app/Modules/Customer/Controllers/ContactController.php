<?php

declare(strict_types=1);

namespace App\Modules\Customer\Controllers;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerContact;
use App\Modules\Customer\Requests\StoreContactRequest;
use App\Modules\Customer\Requests\UpdateContactRequest;
use App\Modules\Customer\Services\CustomerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ContactController
{
    public function __construct(private readonly CustomerService $service) {}

    public function store(StoreContactRequest $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomer($customer);

        $result = $this->service->storeContact($customer, $request->validated());

        return response()->json($result, 201);
    }

    public function update(UpdateContactRequest $request, Customer $customer, CustomerContact $contact): JsonResponse
    {
        $this->authorizeContact($customer, $contact);

        $result = $this->service->updateContact($contact, $request->validated());

        return response()->json($result);
    }

    public function destroy(Request $request, Customer $customer, CustomerContact $contact): JsonResponse
    {
        $this->authorizeContact($customer, $contact);

        $this->service->destroyContact($customer, $contact);

        return response()->json(null, 204);
    }

    private function authorizeCustomer(Customer $customer): void
    {
        if ($customer->user_id !== auth()->id())
        {
            throw new ModelNotFoundException;
        }
    }

    private function authorizeContact(Customer $customer, CustomerContact $contact): void
    {
        $this->authorizeCustomer($customer);

        if ($contact->customer_id !== $customer->id)
        {
            throw new ModelNotFoundException;
        }
    }
}
