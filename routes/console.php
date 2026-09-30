<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires a cron entry: * * * * * php /path/to/current/artisan schedule:run
Schedule::command('notices:publish-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('events:send-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('fees:send-reminders')->dailyAt('09:00')->withoutOverlapping();
