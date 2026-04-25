<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $filename = 'activity-log-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'created_at',
                'event',
                'event_category',
                'actor_type',
                'actor_label',
                'subject_type',
                'subject_label',
                'ip_address',
                'metadata',
            ]);

            ActivityLog::query()
                ->where('created_at', '>=', now()->subYear())
                ->orderByDesc('created_at')
                ->chunk(500, function ($logs) use ($handle): void {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->created_at->toIso8601String(),
                            $log->event->value,
                            $log->event->category(),
                            $log->actor_type,
                            $log->actor_label,
                            $log->subject_type,
                            $log->subject_label,
                            $log->ip_address,
                            $log->metadata ? json_encode($log->metadata) : null,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
