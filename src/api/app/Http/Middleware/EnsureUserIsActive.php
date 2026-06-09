<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user === null || ! $user->is_active)
        {
            return response()->json([
                'message' => 'Account is not activated.',
                'code' => 'inactive_user',
            ], 403);
        }

        return $next($request);
    }
}
