<?php

declare(strict_types=1);

use App\Models\IndustryType;
use App\Models\LlmTemperatureType;
use App\Models\LlmTonalityType;
use App\Modules\ReleaseHistory\Controllers\PublicReleaseHistoryController;
use App\Modules\ReleaseHistory\Controllers\ReleaseHistoryController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/*
 * CLI publish — api_key Bearer auth resolved by AuthenticateWithApiKey.
 */
Route::middleware(['auth:sanctum', 'active'])->group(function (): void
{
    Route::post('/projects/{projectToken}/release-history', [ReleaseHistoryController::class, 'publish']);
});

/*
 * Public release history — no authentication.
 */
Route::prefix('public')->group(function (): void
{
    Route::get('/release-history/{customerSlug}/{projectKey}', [PublicReleaseHistoryController::class, 'index']);
    Route::get('/release-history/{customerSlug}/{projectKey}/translate', [PublicReleaseHistoryController::class, 'translate']);
});

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
