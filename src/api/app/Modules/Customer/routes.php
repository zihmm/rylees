<?php

declare(strict_types=1);

use App\Modules\Customer\Controllers\ContactController;
use App\Modules\Customer\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function (): void
{
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);
    Route::patch('/customers/{customer}', [CustomerController::class, 'update']);

    Route::post('/customers/{customer}/contacts', [ContactController::class, 'store']);
    Route::patch('/customers/{customer}/contacts/{contact}', [ContactController::class, 'update']);
    Route::delete('/customers/{customer}/contacts/{contact}', [ContactController::class, 'destroy']);
});
