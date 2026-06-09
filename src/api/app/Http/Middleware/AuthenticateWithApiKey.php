<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuthenticateWithApiKey
{
    public function handle(Request $request, Closure $next): mixed
    {
        $bearer = $request->bearerToken();

        if ($bearer)
        {
            // Resolve the owning user regardless of activation state; the
            // EnsureUserIsActive ("active") middleware enforces the 403 for
            // inactive accounts so an inactive api_key yields inactive_user,
            // not a generic 401. Soft-deleted users never resolve.
            $user = User::query()
                ->where('api_key', $bearer)
                ->whereNull('deleted_at')
                ->first();

            if ($user)
            {
                Auth::setUser($user);
            }
        }

        return $next($request);
    }
}
