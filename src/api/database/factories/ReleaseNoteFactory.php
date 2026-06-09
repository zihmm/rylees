<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\ReleaseHistory\Models\ReleaseHistory;
use App\Modules\ReleaseHistory\Models\ReleaseNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReleaseNote>
 */
final class ReleaseNoteFactory extends Factory
{
    protected $model = ReleaseNote::class;

    public function definition(): array
    {
        return [
            'release_history_id' => ReleaseHistory::factory(),
            'author_id' => User::factory(),
            'body' => $this->faker->paragraph(),
            'version_major' => 0,
            'version_minor' => 1,
            'version_patch' => 0,
            'branch_name' => 'main',
            'commithash_start' => null,
            'commithash_end' => null,
            'tag_start' => null,
            'tag_end' => null,
        ];
    }
}
