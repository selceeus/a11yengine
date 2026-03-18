<?php

namespace App\Listeners;

use App\Enums\AuditStatus;
use App\Events\ScanCompleted;
use App\Jobs\GenerateAiAuditJob;
use App\Models\Audit;

class GenerateAuditOnScanCompleted
{
    public function handle(ScanCompleted $event): void
    {
        if (! config('ai.audit.auto_generate_on_scan_complete', false)) {
            return;
        }

        $scan = $event->scan;

        $audit = Audit::create([
            'agency_id' => $scan->agency_id,
            'organization_id' => $scan->organization_id,
            'property_id' => $scan->property_id,
            'title' => 'Auto Audit — '.now()->format('M j, Y H:i'),
            'source_scan_ids' => [$scan->id],
            'status' => AuditStatus::Pending,
        ]);

        dispatch(new GenerateAiAuditJob($audit));
    }
}
