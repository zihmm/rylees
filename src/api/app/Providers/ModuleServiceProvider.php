<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\AI\Services\TranslationService;
use Illuminate\Support\ServiceProvider;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TranslationService::class);
    }

    public function boot(): void
    {
        //
    }
}
