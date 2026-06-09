<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Project\Models\Project;
use App\Modules\ReleaseHistory\Models\ReleaseHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReleaseHistory>
 */
final class ReleaseHistoryFactory extends Factory
{
    protected $model = ReleaseHistory::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
        ];
    }
}
