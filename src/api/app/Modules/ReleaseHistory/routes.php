<?php

declare(strict_types=1);

use App\Models\IndustryType;
use App\Models\LlmTemperatureType;
use App\Models\LlmTonalityType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/*
 * Reference data — no authentication.
 */
Route::prefix('ref')->group(function (): void
{
    Route::get('/industries', fn (): JsonResponse => response()->json([
        'items' => IndustryType::query()->orderBy('name')->get(['id', 'name']),
    ]));

    Route::get('/llm-tonalities', fn (): JsonResponse => response()->json([
        'items' => LlmTonalityType::query()->orderBy('name')->get(['id', 'name']),
    ]));

    Route::get('/llm-temperatures', fn (): JsonResponse => response()->json([
        'items' => LlmTemperatureType::query()->orderBy('name')->get(['id', 'name', 'value']),
    ]));
});
