<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IndustryTypeSeeder extends Seeder
{
    public function run(): void
    {
        $industries = [
            'Architecture', 'Consulting', 'Education', 'Finance', 'Healthcare',
            'Legal', 'Manufacturing', 'Marketing', 'Media', 'Real Estate',
            'Retail', 'Technology', 'Other',
        ];

        foreach ($industries as $name)
        {
            DB::table('industry_types')->updateOrInsert(
                ['name' => $name],
                ['id' => (string) Str::uuid(), 'name' => $name]
            );
        }
    }
}
