<?php

declare(strict_types=1);

namespace App\Modules\Account\Controllers;

use App\Modules\Account\Requests\RegisterRequest;
use App\Modules\Account\Requests\UpdateAccountRequest;
use App\Modules\Account\Resources\UserResource;
use App\Modules\Account\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountController
{
    public function __construct(private readonly AccountService $accountService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->accountService->register($request->validated());

        return response()->json($result, 201);
    }

    public function activate(Request $request): JsonResponse
    {
        $this->accountService->activate((string) $request->query('token'));

        return response()->json(['message' => 'Account activated.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile.organisation');

        return response()->json((new UserResource($user))->resolve());
    }

    public function update(UpdateAccountRequest $request): JsonResponse
    {
        $result = $this->accountService->updateMe($request->user(), $request->validated());

        return response()->json($result);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->accountService->destroyMe($request->user());

        return response()->json(null, 204);
    }
}
