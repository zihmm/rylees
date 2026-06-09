<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'username' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'api_key' => Str::random(64),
            'is_active' => true,
            'activation_token' => null,
            'activated_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
            'activation_token' => Str::random(64),
            'activated_at' => null,
        ]);
    }
}
