<?php

declare(strict_types=1);

namespace App\Modules\Customer\Repositories;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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
     * Lean ownership-scoped lookup (no eager loads) for other modules that only
     * need to verify a customer exists and belongs to the given user.
     */
    public function findOwned(string $customerId, string $userId): ?Customer
    {
        return Customer::query()
            ->where('id', $customerId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Resolve a customer by its organisation slug (public release history).
     * The relation constraint excludes soft-deleted organisations.
     */
    public function findByOrganisationSlug(string $slug): ?Customer
    {
        return Customer::query()
            ->whereHas('organisation', static fn (Builder $query): Builder => $query->where('slug', $slug))
            ->first();
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
