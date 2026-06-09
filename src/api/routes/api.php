<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void
{
    require __DIR__.'/../app/Modules/Auth/routes.php';
    require __DIR__.'/../app/Modules/Account/routes.php';
    require __DIR__.'/../app/Modules/Customer/routes.php';
    require __DIR__.'/../app/Modules/Project/routes.php';
    require __DIR__.'/../app/Modules/ReleaseHistory/routes.php';
    require __DIR__.'/../app/Modules/AI/routes.php';
});
