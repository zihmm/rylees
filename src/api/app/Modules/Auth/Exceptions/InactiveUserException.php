<?php

declare(strict_types=1);

namespace App\Modules\Auth\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InactiveUserException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Account is not activated.',
            'code' => 'inactive_user',
        ], 403);
    }
}
