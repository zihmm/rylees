<?php

declare(strict_types=1);

namespace App\Modules\Account\Repositories;

use App\Models\Organisation;
use App\Models\User;
use App\Models\UserProfile;

final class AccountRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createOrganisation(array $attributes): Organisation
    {
        return Organisation::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createUser(array $attributes): User
    {
        return User::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createProfile(array $attributes): UserProfile
    {
        return UserProfile::create($attributes);
    }

    public function findByActivationToken(string $token): ?User
    {
        return User::query()
            ->where('activation_token', $token)
            ->whereNotNull('activation_token')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateProfile(UserProfile $profile, array $attributes): UserProfile
    {
        $profile->update($attributes);

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrganisation(Organisation $organisation, array $attributes): Organisation
    {
        $organisation->update($attributes);

        return $organisation;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateUser(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user;
    }
}
