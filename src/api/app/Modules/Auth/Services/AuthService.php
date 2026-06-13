<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Exceptions\InactiveUserException;
use App\Modules\Auth\Mail\PasswordResetMail;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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

    /**
     * Issue a password reset token and email the reset link.
     *
     * Always succeeds from the caller's perspective: when no account matches
     * the address, nothing happens but no error is surfaced, so the endpoint
     * cannot be used to enumerate registered email addresses.
     */
    public function forgotPassword(string $username): void
    {
        $user = User::query()
            ->where('username', $username)
            ->first();

        if ($user === null)
        {
            return;
        }

        $user->forceFill([
            'password_reset_token' => Str::random(64),
            'password_reset_expires_at' => now()->addMinutes(60),
        ])->save();

        Mail::to($user->username)->send(new PasswordResetMail($user));
    }

    /**
     * Set a new password from a valid, unexpired reset token.
     */
    public function resetPassword(string $token, string $password): void
    {
        $user = User::query()
            ->where('password_reset_token', $token)
            ->whereNotNull('password_reset_token')
            ->where('password_reset_expires_at', '>', now())
            ->first();

        if ($user === null)
        {
            throw new ModelNotFoundException;
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'password_reset_token' => null,
            'password_reset_expires_at' => null,
        ])->save();

        // Revoke any existing sessions so a leaked token cannot outlive the reset.
        $user->tokens()->delete();
    }
}
