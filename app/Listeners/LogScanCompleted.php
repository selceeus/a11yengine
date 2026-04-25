<?php

namespace App\Listeners;

use App\Enums\ActivityLogEvent;
use App\Events\ScanCompleted;
use App\Services\ActivityLogger;

class LogScanCompleted
{
    public function handle(ScanCompleted $event): void
    {
        $scan = $event->scan;

        ActivityLogger::system(
            agencyId: $scan->agency_id,
            event: ActivityLogEvent::ScanCompleted,
            subject: $scan,
            subjectLabel: $scan->property?->name ?? 'Unknown property',
            metadata: [
                'pages_scanned' => $scan->pages_scanned,
                'total_violations' => $scan->total_violations,
            ],
        );
    }
}
