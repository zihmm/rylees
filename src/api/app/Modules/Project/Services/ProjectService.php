<?php

declare(strict_types=1);

namespace App\Modules\Project\Services;

use App\Modules\Customer\Models\Customer;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Repositories\ProjectRepository;
use App\Modules\Project\Resources\ProjectDetailResource;
use App\Modules\ReleaseHistory\Models\ReleaseHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projects,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function store(Customer $customer, array $data): array
    {
        $project = DB::transaction(function () use ($customer, $data): Project
        {
            $project = new Project([
                'customer_id' => $customer->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'llm_tonality_id' => $data['llm_tonality_id'],
                'llm_temperature_id' => $data['llm_temperature_id'],
            ]);

            $project->token = Str::random(64);
            $project->save();

            ReleaseHistory::create(['project_id' => $project->id]);

            return $project;
        });

        return [
            'id' => $project->id,
            'name' => $project->name,
            'key' => $project->key,
            'token' => $project->token,
            'created_at' => $project->created_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(Project $project, array $data): array
    {
        $attributes = $this->onlyPresent($data, ['name', 'description', 'llm_tonality_id', 'llm_temperature_id']);

        if ($attributes !== [])
        {
            $this->projects->update($project, $attributes);
        }

        $project->refresh();

        return (new ProjectDetailResource($this->projects->loadDetail($project)))->resolve();
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
