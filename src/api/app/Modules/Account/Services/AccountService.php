<?php

declare(strict_types=1);

namespace App\Modules\Account\Services;

use App\Models\User;
use App\Modules\Account\Repositories\AccountRepository;
use App\Modules\Account\Resources\UserResource;
use App\Modules\Auth\Mail\AccountActivationMail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AccountService
{
    public function __construct(private readonly AccountRepository $repository) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function register(array $data): array
    {
        $user = DB::transaction(function () use ($data): User
        {
            $organisation = $this->repository->createOrganisation([
                'name' => $data['organisation']['name'],
                'street' => $data['organisation']['street'] ?? null,
                'postcode' => $data['organisation']['postcode'] ?? null,
                'city' => $data['organisation']['city'] ?? null,
                'website' => $data['organisation']['website'] ?? null,
                'email' => $data['organisation']['email'] ?? null,
            ]);

            $user = $this->repository->createUser([
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'api_key' => Str::random(64),
                'is_active' => false,
                'activation_token' => Str::random(64),
            ]);

            $this->repository->createProfile([
                'user_id' => $user->id,
                'firstname' => $data['profile']['firstname'],
                'lastname' => $data['profile']['lastname'],
                'organisation_id' => $organisation->id,
            ]);

            return $user;
        });

        Mail::to($user->username)->send(new AccountActivationMail($user));

        $user->refresh()->load('profile.organisation');

        return [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'is_active' => $user->is_active,
                'activated_at' => $user->activated_at,
                'created_at' => $user->created_at,
            ],
            'profile' => [
                'id' => $user->profile->id,
                'firstname' => $user->profile->firstname,
                'lastname' => $user->profile->lastname,
            ],
            'organisation' => [
                'id' => $user->profile->organisation->id,
                'name' => $user->profile->organisation->name,
                'slug' => $user->profile->organisation->slug,
            ],
        ];
    }

    public function activate(string $token): void
    {
        $user = $this->repository->findByActivationToken($token);

        if ($user === null)
        {
            throw new ModelNotFoundException;
        }

        $this->repository->updateUser($user, [
            'is_active' => true,
            'activated_at' => now(),
            'activation_token' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateMe(User $user, array $data): array
    {
        if (isset($data['profile']))
        {
            $this->repository->updateProfile($user->profile, array_filter([
                'firstname' => $data['profile']['firstname'] ?? null,
                'lastname' => $data['profile']['lastname'] ?? null,
            ], static fn ($value): bool => $value !== null));
        }

        if (isset($data['organisation']))
        {
            $this->repository->updateOrganisation(
                $user->profile->organisation,
                $this->onlyPresent($data['organisation'], ['name', 'street', 'postcode', 'city', 'website', 'email'])
            );
        }

        if (isset($data['new_password']))
        {
            if (! Hash::check($data['current_password'] ?? '', $user->password))
            {
                throw ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }

            $this->repository->updateUser($user, [
                'password' => Hash::make($data['new_password']),
            ]);
        }

        $user->refresh()->load('profile.organisation');

        return (new UserResource($user))->resolve();
    }

    public function destroyMe(User $user): void
    {
        $user->tokens()->delete();
        $user->profile->delete();
        $user->delete();
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function onlyPresent(array $source, array $keys): array
    {
        $result = [];

        foreach ($keys as $key)
        {
            if (array_key_exists($key, $source))
            {
                $result[$key] = $source[$key];
            }
        }

        return $result;
    }
}
