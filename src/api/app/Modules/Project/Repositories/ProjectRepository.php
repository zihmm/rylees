<?php

declare(strict_types=1);

namespace App\Modules\Project\Repositories;

use App\Modules\Customer\Models\Customer;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Collection;

final class ProjectRepository
{
    /**
     * @return Collection<int, Project>
     */
    public function forCustomer(Customer $customer): Collection
    {
        return Project::query()
            ->where('customer_id', $customer->id)
            ->with(['tonality', 'temperature'])
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
