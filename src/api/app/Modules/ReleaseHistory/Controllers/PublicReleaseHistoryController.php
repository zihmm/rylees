<?php

declare(strict_types=1);

namespace App\Modules\ReleaseHistory\Controllers;

use App\Models\Organisation;
use App\Modules\Customer\Models\Customer;
use App\Modules\Project\Models\Project;
use App\Modules\ReleaseHistory\Repositories\ReleaseHistoryRepository;
use App\Modules\ReleaseHistory\Resources\ReleaseNoteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicReleaseHistoryController
{
    public function __construct(
        private readonly ReleaseHistoryRepository $repository,
    ) {}

    public function index(Request $request, string $customerSlug, string $projectKey): JsonResponse
    {
        $project = $this->resolveProject($customerSlug, $projectKey);
        $history = $project->releaseHistory;

        $notes = $this->repository->orderedNotes($history);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'key' => $project->key,
            ],
            'items' => ReleaseNoteResource::collection($notes)->resolve(),
        ]);
    }

    public function translate(Request $request, string $customerSlug, string $projectKey): JsonResponse
    {
        $validated = $request->validate([
            'language' => 'required|in:de,en,fr',
        ]);

        $project = $this->resolveProject($customerSlug, $projectKey);
        $history = $project->releaseHistory;

        $notes = $this->repository->orderedNotes($history);

        $service = app(\App\Modules\AI\Services\TranslationService::class);

        $translated = $service->translate(
            $notes->map(fn ($n): array => ['id' => $n->id, 'body' => $n->body])->toArray(),
            $validated['language'],
        );

        return response()->json([
            'language' => $validated['language'],
            'items' => $translated,
        ]);
    }

    private function resolveProject(string $customerSlug, string $projectKey): Project
    {
        $organisation = Organisation::query()
            ->where('slug', $customerSlug)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $customer = Customer::query()
            ->where('organisation_id', $organisation->id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        return Project::query()
            ->where('customer_id', $customer->id)
            ->where('key', $projectKey)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }
}
