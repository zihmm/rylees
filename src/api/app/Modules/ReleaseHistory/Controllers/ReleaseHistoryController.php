<?php

declare(strict_types=1);

namespace App\Modules\ReleaseHistory\Controllers;

use App\Modules\Project\Services\ProjectService;
use App\Modules\ReleaseHistory\Requests\PublishReleaseNoteRequest;
use App\Modules\ReleaseHistory\Services\ReleaseHistoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

final class ReleaseHistoryController
{
    public function __construct(
        private readonly ReleaseHistoryService $service,
        private readonly ProjectService $projects,
    ) {}

    public function publish(PublishReleaseNoteRequest $request, string $projectToken): JsonResponse
    {
        // Resolve and authorize the project through the Project module's public
        // service; this module never queries the projects table directly.
        $project = $this->projects->findByToken($projectToken);

        if ($project === null)
        {
            throw new ModelNotFoundException;
        }

        if (! $this->projects->isOwnedBy($project, (string) auth()->id()))
        {
            return response()->json(['message' => 'Forbidden.', 'code' => 'forbidden'], 403);
        }

        // A project created through the API always has a release history, but one
        // inserted by a seeder/factory may not — provision it on first publish.
        $history = $project->releaseHistory ?? $this->service->provisionForProject($project->id);

        $result = $this->service->publish($history, $request->validated(), (string) auth()->id());

        return response()->json($result, 201);
    }
}
