<?php

declare(strict_types=1);

namespace App\Modules\Customer\Services;

use App\Models\Organisation;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerContact;
use App\Modules\Customer\Repositories\ContactRepository;
use App\Modules\Customer\Repositories\CustomerRepository;
use App\Modules\Customer\Resources\ContactResource;
use App\Modules\Customer\Resources\CustomerDetailResource;
use Illuminate\Support\Facades\DB;

final class CustomerService
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly ContactRepository $contacts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function store(array $data, string $userId): array
    {
        $customer = DB::transaction(function () use ($data, $userId): Customer
        {
            $organisation = Organisation::create([
                'name' => $data['organisation']['name'],
                'street' => $data['organisation']['street'] ?? null,
                'postcode' => $data['organisation']['postcode'] ?? null,
                'city' => $data['organisation']['city'] ?? null,
                'website' => $data['organisation']['website'] ?? null,
                'email' => $data['organisation']['email'] ?? null,
            ]);

            $customer = $this->customers->create([
                'user_id' => $userId,
                'organisation_id' => $organisation->id,
                'industry_id' => $data['industry_id'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            if (isset($data['main_contact']))
            {
                $contact = $this->contacts->create([
                    'customer_id' => $customer->id,
                    'firstname' => $data['main_contact']['firstname'],
                    'lastname' => $data['main_contact']['lastname'],
                    'email' => $data['main_contact']['email'],
                ]);

                $customer->main_contact_id = $contact->id;
                $customer->save();
            }

            return $customer;
        });

        $customer->load('organisation');

        return [
            'id' => $customer->id,
            'organisation' => [
                'id' => $customer->organisation->id,
                'name' => $customer->organisation->name,
                'slug' => $customer->organisation->slug,
            ],
            'created_at' => $customer->created_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(Customer $customer, array $data): array
    {
        DB::transaction(function () use ($customer, $data): void
        {
            if (isset($data['organisation']))
            {
                $customer->organisation->update(
                    $this->onlyPresent($data['organisation'], ['name', 'street', 'postcode', 'city', 'website', 'email'])
                );
            }

            $customerAttributes = $this->onlyPresent($data, ['industry_id', 'description']);

            if ($customerAttributes !== [])
            {
                $this->customers->update($customer, $customerAttributes);
            }
        });

        $customer->refresh()->load(['organisation', 'mainContact', 'industry', 'contacts', 'projects']);

        return (new CustomerDetailResource($customer))->resolve();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function storeContact(Customer $customer, array $data): array
    {
        $contact = $this->contacts->create([
            'customer_id' => $customer->id,
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'email' => $data['email'],
        ]);

        return (new ContactResource($contact))->resolve();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateContact(CustomerContact $contact, array $data): array
    {
        $this->contacts->update(
            $contact,
            $this->onlyPresent($data, ['firstname', 'lastname', 'email'])
        );

        return (new ContactResource($contact))->resolve();
    }

    public function destroyContact(Customer $customer, CustomerContact $contact): void
    {
        DB::transaction(function () use ($customer, $contact): void
        {
            $this->contacts->delete($contact);

            if ($customer->main_contact_id === $contact->id)
            {
                $customer->main_contact_id = null;
                $customer->save();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function onlyPresent(array $source, array $keys): array
    {
        $result = [];

        foreach ($keys as $key)
        {
            if (array_key_exists($key, $source))
            {
                $result[$key] = $source[$key];
            }
        }

        return $result;
    }
}
