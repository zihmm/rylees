<?php

declare(strict_types=1);

namespace App\Modules\Project\Controllers;

use App\Modules\Customer\Models\Customer;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Repositories\ProjectRepository;
use App\Modules\Project\Requests\StoreProjectRequest;
use App\Modules\Project\Requests\UpdateProjectRequest;
use App\Modules\Project\Resources\ProjectDetailResource;
use App\Modules\Project\Resources\ProjectListResource;
use App\Modules\Project\Services\ProjectService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProjectController
{
    public function __construct(
        private readonly ProjectService $service,
        private readonly ProjectRepository $repository,
    ) {}

    public function index(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomer($customer);

        $projects = $this->repository->forCustomer($customer);

        return response()->json([
            'data' => ProjectListResource::collection($projects)->resolve(),
        ]);
    }

    public function store(StoreProjectRequest $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomer($customer);

        $result = $this->service->store($customer, $request->validated());

        return response()->json($result, 201);
    }

    public function show(Request $request, Customer $customer, Project $project): JsonResponse
    {
        $this->authorizeProject($customer, $project);

        $project = $this->repository->loadDetail($project);

        return response()->json((new ProjectDetailResource($project))->resolve());
    }

    public function update(UpdateProjectRequest $request, Customer $customer, Project $project): JsonResponse
    {
        $this->authorizeProject($customer, $project);

        $result = $this->service->update($project, $request->validated());

        return response()->json($result);
    }

    public function showByToken(Request $request, string $projectToken): JsonResponse
    {
        $project = Project::query()->where('token', $projectToken)->firstOrFail();

        if ($project->customer->user_id !== auth()->id())
        {
            return response()->json(['message' => 'Forbidden.', 'code' => 'forbidden'], 403);
        }

        $project = $this->repository->loadDetail($project);

        return response()->json((new ProjectDetailResource($project))->resolve());
    }

    private function authorizeCustomer(Customer $customer): void
    {
        if ($customer->user_id !== auth()->id())
        {
            throw new ModelNotFoundException;
        }
    }

    private function authorizeProject(Customer $customer, Project $project): void
    {
        $this->authorizeCustomer($customer);

        if ($project->customer_id !== $customer->id)
        {
            throw new ModelNotFoundException;
        }
    }
}
