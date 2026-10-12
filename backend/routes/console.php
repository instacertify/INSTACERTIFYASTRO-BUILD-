<?php

use App\Jobs\PublishWebsite;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('privacy:prune')->dailyAt('03:15')->withoutOverlapping();
// Drops expired vacancies and past events from the static site even when nobody edits anything.
Schedule::job(new PublishWebsite)->dailyAt('03:30');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
