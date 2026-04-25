<?php

namespace App\Listeners;

use App\Enums\ActivityLogEvent;
use App\Events\ScanFailed;
use App\Services\ActivityLogger;

class LogScanFailed
{
    public function handle(ScanFailed $event): void
    {
        $scan = $event->scan;

        ActivityLogger::system(
            agencyId: $scan->agency_id,
            event: ActivityLogEvent::ScanFailed,
            subject: $scan,
            subjectLabel: $scan->property?->name ?? 'Unknown property',
            metadata: [
                'error_message' => $scan->error_message,
            ],
        );
    }
}
