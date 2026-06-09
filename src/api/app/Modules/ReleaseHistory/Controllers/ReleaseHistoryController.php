<?php

declare(strict_types=1);

namespace App\Modules\ReleaseHistory\Controllers;

use App\Modules\Project\Models\Project;
use App\Modules\ReleaseHistory\Requests\PublishReleaseNoteRequest;
use App\Modules\ReleaseHistory\Services\ReleaseHistoryService;
use Illuminate\Http\JsonResponse;

final class ReleaseHistoryController
{
    public function __construct(
        private readonly ReleaseHistoryService $service,
    ) {}

    public function publish(PublishReleaseNoteRequest $request, string $projectToken): JsonResponse
    {
        $project = Project::query()->where('token', $projectToken)->firstOrFail();

        if ($project->customer->user_id !== auth()->id())
        {
            return response()->json(['message' => 'Forbidden.', 'code' => 'forbidden'], 403);
        }

        $result = $this->service->publish($project, $request->validated(), (string) auth()->id());

        return response()->json($result, 201);
    }
}
