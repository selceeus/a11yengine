<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('snapshots:property-risk')->daily();
Schedule::command('snapshots:organization-risk')->daily();
Schedule::command('snapshots:agency-risk')->daily();
Schedule::command('scans:run-scheduled')->everyMinute();
Schedule::command('scans:expire-stuck')->everyFiveMinutes();
Schedule::command('governance:generate-reports')->weekly();
Schedule::command('digest:weekly')->weeklyOn(1, '9:00');

// RAG re-indexing
Schedule::command('rag:reindex-remediations')->weeklyOn(0, '02:00');
Schedule::command('rag:index-wcag', ['--skip-if-indexed' => true])->monthly();
Schedule::command('rag:index-lawsuits', ['--skip-if-indexed' => true])->monthly();
Schedule::command('access-reviews:create')->quarterly();
Schedule::command('api-keys:notify-expiring')->daily();
Schedule::command('api-keys:revoke-expired')->daily();
Schedule::command('activity-log:prune')->monthly();
