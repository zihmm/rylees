<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LlmTonalityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $tonalities = ['neutral', 'professional', 'friendly', 'humorous'];

        foreach ($tonalities as $name)
        {
            if (DB::table('llm_tonality_types')->where('name', $name)->doesntExist())
            {
                DB::table('llm_tonality_types')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $name,
                ]);
            }
        }
    }
}
