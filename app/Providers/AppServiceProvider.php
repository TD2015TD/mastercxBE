<?php

namespace App\Providers;

use App\Services\MitelService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register MitelService as singleton
        $this->app->singleton(MitelService::class, function ($app) {
            return new MitelService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
