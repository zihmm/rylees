<?php

declare(strict_types=1);

namespace App\Modules\Project\Services;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Repositories\ProjectRepository;
use App\Modules\Project\Resources\ProjectDetailResource;
use App\Modules\ReleaseHistory\Services\ReleaseHistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ReleaseHistoryService $releaseHistories,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function store(string $customerId, array $data): array
    {
        $project = DB::transaction(function () use ($customerId, $data): Project
        {
            $project = new Project([
                'customer_id' => $customerId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'language' => $data['language'] ?? 'en',
                'llm_tonality_id' => $data['llm_tonality_id'],
                'llm_temperature_id' => $data['llm_temperature_id'],
            ]);

            $project->token = Str::random(64);
            $project->save();

            // Provisioning the release history is the ReleaseHistory module's
            // responsibility — delegate instead of writing its table here.
            $this->releaseHistories->initialiseForProject($project->id);

            return $project;
        });

        return [
            'id' => $project->id,
            'name' => $project->name,
            'key' => $project->key,
            'language' => $project->language,
            'token' => $project->token,
            'created_at' => $project->created_at,
        ];
    }

    /**
     * Public lookup used by other modules (e.g. ReleaseHistory) to resolve a
     * project by its CLI token without touching the projects table directly.
     */
    public function findByToken(string $token): ?Project
    {
        return Project::query()->where('token', $token)->first();
    }

    /**
     * Public lookup used by the ReleaseHistory module to resolve a project from
     * a customer + project key (public release history URLs).
     */
    public function findByCustomerAndKey(string $customerId, string $projectKey): ?Project
    {
        return Project::query()
            ->where('customer_id', $customerId)
            ->where('key', $projectKey)
            ->first();
    }

    /**
     * Ownership check exposed for other modules: a project belongs to the user
     * when its customer does.
     */
    public function isOwnedBy(Project $project, string $userId): bool
    {
        return $project->customer->user_id === $userId;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(Project $project, array $data): array
    {
        $attributes = $this->onlyPresent($data, ['name', 'description', 'language', 'llm_tonality_id', 'llm_temperature_id']);

        if ($attributes !== [])
        {
            $this->projects->update($project, $attributes);
        }

        $project->refresh();

        return (new ProjectDetailResource($this->projects->loadDetail($project)))->resolve();
    }

    /**
     * Delete a project and cascade the deletion to its release history and
     * release notes. Both the project and its dependants use SoftDeletes, so
     * this is a soft delete consistent with the rest of the app.
     */
    public function destroy(Project $project): void
    {
        DB::transaction(function () use ($project): void
        {
            // Deleting the release history is the ReleaseHistory module's
            // responsibility — delegate instead of touching its tables here.
            $this->releaseHistories->deleteForProject($project->id);

            $project->delete();
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
