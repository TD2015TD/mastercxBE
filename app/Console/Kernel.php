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

        // Email Processing - Run every minute to check for new emails
        $schedule->command('email:process --once')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::debug('Email processing check completed');
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Email processing failed');
            });

        // Alternative schedules (comment/uncomment as needed):

        // More frequent monitoring (every 5 minutes) for high-traffic systems:
        // $schedule->command('sla:monitor')->everyFiveMinutes()->withoutOverlapping();

        // Less frequent monitoring (every 30 minutes) for lower-traffic systems:
        // $schedule->command('sla:monitor')->everyThirtyMinutes()->withoutOverlapping();

        // Run only during business hours (8 AM to 6 PM):
        // $schedule->command('sla:monitor')->everyFifteenMinutes()->between('8:00', '18:00')->withoutOverlapping();

        // Email processing alternatives:
        // Less frequent email checks (every 5 minutes):
        // $schedule->command('email:process --once')->everyFiveMinutes()->withoutOverlapping();

        // Email processing during business hours only:
        // $schedule->command('email:process --once')->everyMinute()->between('8:00', '18:00')->withoutOverlapping();

        // Mitel Data Sync - Sync all active connections based on config
        //$syncSchedule = config('mitel.sync_schedule', '*/30 * * * *');
        $schedule->command('mitel:sync --all')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Mitel data sync completed successfully');
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Mitel data sync failed');
            });

        // Alternative Mitel sync schedules (comment/uncomment as needed):

        // More frequent sync (every 15 minutes):
        // $schedule->command('mitel:sync --all')->everyFifteenMinutes()->withoutOverlapping();

        // Hourly sync:
        // $schedule->command('mitel:sync --all')->hourly()->withoutOverlapping();

        // Daily sync at specific time:
        // $schedule->command('mitel:sync --all')->dailyAt('02:00')->withoutOverlapping();

        // Business hours only (8 AM to 6 PM):
        // $schedule->command('mitel:sync --all')->cron($syncSchedule)->between('8:00', '18:00')->withoutOverlapping();
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
