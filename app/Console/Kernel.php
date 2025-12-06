<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // SLA Monitor - Run every 15 minutes
        $schedule->command('sla:monitor')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('SLA Monitor completed successfully');
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('SLA Monitor failed');
            });

        // Alternative schedules (comment/uncomment as needed):
        
        // More frequent monitoring (every 5 minutes) for high-traffic systems:
        // $schedule->command('sla:monitor')->everyFiveMinutes()->withoutOverlapping();
        
        // Less frequent monitoring (every 30 minutes) for lower-traffic systems:
        // $schedule->command('sla:monitor')->everyThirtyMinutes()->withoutOverlapping();
        
        // Run only during business hours (8 AM to 6 PM):
        // $schedule->command('sla:monitor')->everyFifteenMinutes()->between('8:00', '18:00')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
