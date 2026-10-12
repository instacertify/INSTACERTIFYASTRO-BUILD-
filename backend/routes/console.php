<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('privacy:prune')->dailyAt('03:15')->withoutOverlapping();
// Republishes only when a date changes what the website shows, e.g. an event ends or a scheduled post goes live.
Schedule::command('site:publish-due')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
