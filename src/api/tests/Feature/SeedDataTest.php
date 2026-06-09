<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

it('returns seeded industries from the ref endpoint', function (): void
{
    $this->seed(DatabaseSeeder::class);

    $this->getJson('/v1/ref/industries')
        ->assertOk()
        ->assertJsonCount(13, 'items')
        ->assertJsonFragment(['name' => 'Architecture']);
});

it('returns 4 llm tonalities from the ref endpoint', function (): void
{
    $this->seed(DatabaseSeeder::class);

    $this->getJson('/v1/ref/llm-tonalities')
        ->assertOk()
        ->assertJsonCount(4, 'items');
});

it('returns 3 llm temperatures from the ref endpoint', function (): void
{
    $this->seed(DatabaseSeeder::class);

    $this->getJson('/v1/ref/llm-temperatures')
        ->assertOk()
        ->assertJsonCount(3, 'items')
        ->assertJsonFragment(['name' => 'balanced', 'value' => 0.5]);
});

it('seeds idempotently without creating duplicate rows', function (): void
{
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(DB::table('industry_types')->count())->toBe(13);
    expect(DB::table('llm_tonality_types')->count())->toBe(4);
    expect(DB::table('llm_temperature_types')->count())->toBe(3);
});
