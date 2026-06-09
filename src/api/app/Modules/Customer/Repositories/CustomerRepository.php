<?php

declare(strict_types=1);

namespace App\Modules\Customer\Repositories;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CustomerRepository
{
    public function paginatedForUser(User $user, int $page, int $perPage): LengthAwarePaginator
    {
        return Customer::query()
            ->where('user_id', $user->id)
            ->withCount('projects')
            ->with(['organisation', 'mainContact', 'industry'])
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function findForUser(string $id, User $user): ?Customer
    {
        return Customer::query()
            ->where('user_id', $user->id)
            ->with(['organisation', 'mainContact', 'industry', 'contacts', 'projects'])
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Customer
    {
        return Customer::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Customer $customer, array $attributes): Customer
    {
        $customer->update($attributes);

        return $customer;
    }
}
