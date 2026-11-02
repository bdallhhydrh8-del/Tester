<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\CleanupFilesJob;
use Illuminate\Support\Facades\Schedule;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new CleanupFilesJob)->dailyAt('03:00');
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('disk:check')->hourly();
