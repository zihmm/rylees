<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LlmTemperatureType;
use App\Models\LlmTonalityType;
use App\Modules\Customer\Models\Customer;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
final class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => $this->faker->unique()->words(3, true),
            'description' => $this->faker->sentence(),
            'language' => 'en',
            'token' => Str::random(64),
            'llm_tonality_id' => LlmTonalityType::firstOrCreate(
                ['name' => 'professional'],
                ['id' => (string) Str::uuid(), 'name' => 'professional']
            )->id,
            'llm_temperature_id' => LlmTemperatureType::firstOrCreate(
                ['name' => 'balanced'],
                ['id' => (string) Str::uuid(), 'name' => 'balanced', 'value' => 0.5]
            )->id,
        ];
    }
}
