<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LlmTemperatureTypeSeeder extends Seeder
{
    public function run(): void
    {
        $temperatures = [
            ['name' => 'precise', 'value' => 0.2],
            ['name' => 'balanced', 'value' => 0.5],
            ['name' => 'creative', 'value' => 0.8],
        ];

        foreach ($temperatures as $item)
        {
            $existing = DB::table('llm_temperature_types')->where('name', $item['name'])->first();

            if ($existing === null)
            {
                DB::table('llm_temperature_types')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $item['name'],
                    'value' => $item['value'],
                ]);

                continue;
            }

            DB::table('llm_temperature_types')
                ->where('id', $existing->id)
                ->update(['value' => $item['value']]);
        }
    }
}
