<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Monitoring Scheduler
|--------------------------------------------------------------------------
*/

Schedule::command('monitor:run')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('monitor:snapshot')
    ->everyTwoMinutes()
    ->withoutOverlapping(20);

Schedule::command('monitoring:cleanup')
    ->dailyAt('01:00')
    ->withoutOverlapping();

