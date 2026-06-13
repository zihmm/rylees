<?php

declare(strict_types=1);

use App\Modules\Project\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function ()
{
    // Global overview of every project owned by the authenticated developer.
    Route::get('/projects', [ProjectController::class, 'all']);

    Route::get('/customers/{customer}/projects', [ProjectController::class, 'index']);
    Route::post('/customers/{customer}/projects', [ProjectController::class, 'store']);
    Route::get('/customers/{customer}/projects/{project}', [ProjectController::class, 'show']);
    Route::patch('/customers/{customer}/projects/{project}', [ProjectController::class, 'update']);

    // CLI generation — resolves a project by its token (used by the rylees CLI).
    Route::get('/projects/{projectToken}', [ProjectController::class, 'showByToken']);
});
