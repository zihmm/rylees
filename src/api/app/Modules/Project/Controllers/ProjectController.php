<?php

declare(strict_types=1);

namespace App\Modules\Project\Controllers;

use App\Modules\Customer\Services\CustomerService;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Repositories\ProjectRepository;
use App\Modules\Project\Requests\StoreProjectRequest;
use App\Modules\Project\Requests\UpdateProjectRequest;
use App\Modules\Project\Resources\ProjectDetailResource;
use App\Modules\Project\Resources\ProjectListResource;
use App\Modules\Project\Resources\ProjectOverviewResource;
use App\Modules\Project\Services\ProjectService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProjectController
{
    public function __construct(
        private readonly ProjectService $service,
        private readonly ProjectRepository $repository,
        private readonly CustomerService $customers,
    ) {}

    public function all(Request $request): JsonResponse
    {
        $projects = $this->repository->forUser(auth()->id());

        return response()->json([
            'data' => ProjectOverviewResource::collection($projects)->resolve(),
        ]);
    }

    public function index(Request $request, string $customer): JsonResponse
    {
        $owned = $this->resolveOwnedCustomer($customer);

        $projects = $this->repository->forCustomer($owned->id);

        return response()->json([
            'data' => ProjectListResource::collection($projects)->resolve(),
        ]);
    }

    public function store(StoreProjectRequest $request, string $customer): JsonResponse
    {
        $owned = $this->resolveOwnedCustomer($customer);

        $result = $this->service->store($owned->id, $request->validated());

        return response()->json($result, 201);
    }

    public function show(Request $request, string $customer, Project $project): JsonResponse
    {
        $owned = $this->resolveOwnedCustomer($customer);
        $this->ensureProjectBelongsTo($project, $owned->id);

        $project = $this->repository->loadDetail($project);

        return response()->json((new ProjectDetailResource($project))->resolve());
    }

    public function update(UpdateProjectRequest $request, string $customer, Project $project): JsonResponse
    {
        $owned = $this->resolveOwnedCustomer($customer);
        $this->ensureProjectBelongsTo($project, $owned->id);

        $result = $this->service->update($project, $request->validated());

        return response()->json($result);
    }

    public function showByToken(Request $request, string $projectToken): JsonResponse
    {
        $project = $this->service->findByToken($projectToken);

        if ($project === null)
        {
            throw new ModelNotFoundException;
        }

        if (! $this->service->isOwnedBy($project, (string) auth()->id()))
        {
            return response()->json(['message' => 'Forbidden.', 'code' => 'forbidden'], 403);
        }

        $project = $this->repository->loadDetail($project);

        return response()->json((new ProjectDetailResource($project))->resolve());
    }

    /**
     * Resolve the {customer} route segment through the Customer module's public
     * service, scoped to the authenticated developer. A customer that does not
     * exist or is not owned surfaces as 404 not_found (no existence leak).
     */
    private function resolveOwnedCustomer(string $customerId): object
    {
        $customer = $this->customers->findForUser($customerId, (string) auth()->id());

        if ($customer === null)
        {
            throw new ModelNotFoundException;
        }

        return $customer;
    }

    private function ensureProjectBelongsTo(Project $project, string $customerId): void
    {
        if ($project->customer_id !== $customerId)
        {
            throw new ModelNotFoundException;
        }
    }
}
