<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

app(Schedule::class)->call(function () {
    //
})->name('noop'); // keeps file from being empty

// SLA Monitor – every 15 minutes
app(Schedule::class)
    ->command('sla:monitor')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(fn () => Log::info('SLA Monitor completed successfully'))
    ->onFailure(fn () => Log::error('SLA Monitor failed'));

// Email processing – every minute
app(Schedule::class)
    ->command('email:process --once')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(fn () => Log::debug('Email processing completed'))
    ->onFailure(fn () => Log::error('Email processing failed'));

// Mitel Data Sync
$syncSchedule = config('mitel.sync_schedule', '*/30 * * * *');

app(Schedule::class)
    ->command('mitel:sync --all')
    ->cron($syncSchedule)
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(fn () => Log::info('Mitel data sync completed successfully'))
    ->onFailure(fn () => Log::error('Mitel data sync failed'));