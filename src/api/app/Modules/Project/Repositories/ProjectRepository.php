<?php

declare(strict_types=1);

namespace App\Modules\Project\Repositories;

use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class ProjectRepository
{
    /**
     * @return Collection<int, Project>
     */
    public function forCustomer(string $customerId): Collection
    {
        return Project::query()
            ->where('customer_id', $customerId)
            ->with(['tonality', 'temperature'])
            ->get();
    }

    /**
     * All projects across every customer owned by the given user, with the
     * data needed for the console overview list (customer name, latest version).
     *
     * @return Collection<int, Project>
     */
    public function forUser(string $userId): Collection
    {
        return Project::query()
            ->whereHas('customer', static fn (Builder $query): Builder => $query->where('user_id', $userId))
            ->with(['customer.organisation', 'releaseHistory.latestReleaseNote'])
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Project $project, array $attributes): Project
    {
        $project->update($attributes);

        return $project;
    }

    public function loadDetail(Project $project): Project
    {
        return $project->load(['customer.organisation', 'customer.industry', 'tonality', 'temperature']);
    }
}
