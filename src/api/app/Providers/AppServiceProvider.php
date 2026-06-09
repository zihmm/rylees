<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\AuthenticateWithApiKey;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The api_key resolver must run BEFORE Sanctum's Authenticate
        // middleware so a Bearer api_key sets the user that the
        // auth:sanctum guard then sees. Authenticate carries middleware
        // priority, so we prepend our resolver ahead of it.
        $this->app->make(Kernel::class)
            ->prependToMiddlewarePriority(AuthenticateWithApiKey::class);
    }
}
