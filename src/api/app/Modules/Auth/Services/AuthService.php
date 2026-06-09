<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Exceptions\InactiveUserException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

final class AuthService
{
    /**
     * @param  array{username: string, password: string}  $credentials
     * @return array<string, mixed>
     */
    public function login(array $credentials): array
    {
        $user = User::query()
            ->where('username', $credentials['username'])
            ->first();

        if ($user === null)
        {
            throw new AuthenticationException;
        }

        if (! Hash::check($credentials['password'], $user->password))
        {
            throw new AuthenticationException;
        }

        if (! $user->is_active)
        {
            throw new InactiveUserException;
        }

        $token = $user->createToken('web', ['*'], now()->addMinutes(60));

        return [
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_in' => 3600,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'is_active' => $user->is_active,
                'profile' => [
                    'id' => $user->profile->id,
                    'firstname' => $user->profile->firstname,
                    'lastname' => $user->profile->lastname,
                ],
                'organisation' => [
                    'id' => $user->profile->organisation->id,
                    'name' => $user->profile->organisation->name,
                ],
            ],
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
