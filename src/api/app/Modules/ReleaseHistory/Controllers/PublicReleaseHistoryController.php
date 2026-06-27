<?php

declare(strict_types=1);

namespace App\Modules\ReleaseHistory\Controllers;

use App\Modules\AI\Services\TranslationService;
use App\Modules\Customer\Services\CustomerService;
use App\Modules\Project\Services\ProjectService;
use App\Modules\ReleaseHistory\Repositories\ReleaseHistoryRepository;
use App\Modules\ReleaseHistory\Resources\ReleaseNoteResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class PublicReleaseHistoryController
{
    public function __construct(
        private readonly ReleaseHistoryRepository $repository,
        private readonly CustomerService $customers,
        private readonly ProjectService $projects,
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

        $translated = app(TranslationService::class)->translate(
            $notes->map(fn ($n): array => ['id' => $n->id, 'body' => $n->body])->toArray(),
            $validated['language'],
        );

        // The AI returns only the translated "body" per id; "version" is
        // reconstructed from the note's own version columns, never the AI.
        $bodyById = Collection::make($translated)->keyBy('id');

        $items = $notes->map(fn ($n): array => [
            'id' => $n->id,
            'version' => "{$n->version_major}.{$n->version_minor}.{$n->version_patch}",
            'body' => $bodyById[$n->id]['body'] ?? $n->body,
        ])->values();

        return response()->json([
            'language' => $validated['language'],
            'items' => $items,
        ]);
    }

    /**
     * Resolve the public {customerSlug}/{projectKey} pair through the owning
     * modules' service interfaces. Returns the Project model (read-only here);
     * a missing customer or project surfaces as 404 not_found.
     */
    private function resolveProject(string $customerSlug, string $projectKey): object
    {
        $customer = $this->customers->findByOrganisationSlug($customerSlug);

        if ($customer === null)
        {
            throw new ModelNotFoundException;
        }

        $project = $this->projects->findByCustomerAndKey($customer->id, $projectKey);

        if ($project === null)
        {
            throw new ModelNotFoundException;
        }

        return $project;
    }
}
