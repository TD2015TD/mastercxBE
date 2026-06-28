<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Observers\TicketObserver;
use App\Services\McxOpenMediaService;
use App\Services\MitelService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MitelService::class, function ($app) {
            return new MitelService();
        });

        $this->app->singleton(McxOpenMediaService::class, function ($app) {
            return new McxOpenMediaService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Ticket::observe(TicketObserver::class);
    }
}
