<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('snapshots:agency-risk')->daily();
Schedule::command('scans:run-scheduled')->everyMinute();
Schedule::command('governance:generate-reports')->weekly();
Schedule::command('digest:weekly')->weeklyOn(1, '9:00');
