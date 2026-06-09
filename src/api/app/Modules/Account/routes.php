<?php

declare(strict_types=1);

use App\Modules\Account\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

Route::post('/users/register', [AccountController::class, 'register']);
Route::get('/users/activate', [AccountController::class, 'activate']);

Route::middleware(['auth:sanctum', 'active'])->group(function (): void
{
    Route::get('/users/me', [AccountController::class, 'me']);
    Route::patch('/users/me', [AccountController::class, 'update']);
    Route::delete('/users/me', [AccountController::class, 'destroy']);
});
