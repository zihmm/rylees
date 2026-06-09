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
            $user = User::query()
                ->where('api_key', $bearer)
                ->where('is_active', true)
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
