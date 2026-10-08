<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily price update: scrape every enabled store, import, send price-drop alerts.
// Needs `php artisan schedule:run` every minute (Task Scheduler / cron) or `php artisan schedule:work`.
Schedule::command('products:scrape')
    ->dailyAt(config('services.scraper.daily_at'))
    ->timezone('Europe/Riga')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/scrape.log'));

// Keeps scrape history and scrape.log from growing forever and clears runs stuck at "running".
Schedule::command('products:housekeeping')
    ->dailyAt('03:30')
    ->timezone('Europe/Riga');
