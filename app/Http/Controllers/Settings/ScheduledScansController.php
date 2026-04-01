<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Scan;
use App\Models\ScheduledScan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class ScheduledScansController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', \App\Models\Scan::class);

        $scheduledScans = ScheduledScan::query()
            ->with('property:id,name,base_url', 'organization:id,name')
            ->latest()
            ->get()
            ->map(fn (ScheduledScan $s) => [
                'id' => $s->id,
                'type' => $s->type,
                'frequency' => $s->frequency,
                'is_active' => $s->is_active,
                'next_run_at' => $s->next_run_at?->toIso8601String(),
                'last_run_at' => $s->last_run_at?->toIso8601String(),
                'run_time' => $s->run_time,
                'property' => $s->property ? [
                    'id' => $s->property->id,
                    'name' => $s->property->name,
                    'base_url' => $s->property->base_url,
                ] : null,
                'organization' => $s->organization ? [
                    'id' => $s->organization->id,
                    'name' => $s->organization->name,
                ] : null,
            ]);

        return Inertia::render('settings/scheduled-scans', [
            'scheduledScans' => $scheduledScans,
            'properties' => app('currentAgency')->properties()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(ScheduledScan $scheduledScan): Response
    {
        $this->authorize('viewAny', Scan::class);

        $scheduledScan->load('property:id,name,base_url', 'organization:id,name');

        $recentScans = $scheduledScan->property
            ? Scan::query()
                ->where('property_id', $scheduledScan->property_id)
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (Scan $s) => [
                    'id' => $s->id,
                    'status' => $s->status->value,
                    'pages_scanned' => $s->pages_scanned,
                    'total_violations' => $s->total_violations,
                    'started_at' => $s->started_at?->toIso8601String(),
                    'completed_at' => $s->completed_at?->toIso8601String(),
                ])
            : [];

        return Inertia::render('settings/scheduled-scan-show', [
            'scheduledScan' => [
                'id' => $scheduledScan->id,
                'type' => $scheduledScan->type,
                'frequency' => $scheduledScan->frequency,
                'scheduled_at' => $scheduledScan->scheduled_at?->toIso8601String(),
                'run_time' => $scheduledScan->run_time,
                'timezone' => $scheduledScan->timezone,
                'run_day_of_week' => $scheduledScan->run_day_of_week,
                'run_day_of_month' => $scheduledScan->run_day_of_month,
                'next_run_at' => $scheduledScan->next_run_at?->toIso8601String(),
                'last_run_at' => $scheduledScan->last_run_at?->toIso8601String(),
                'is_active' => $scheduledScan->is_active,
                'property' => $scheduledScan->property ? [
                    'id' => $scheduledScan->property->id,
                    'name' => $scheduledScan->property->name,
                    'base_url' => $scheduledScan->property->base_url,
                ] : null,
                'organization' => $scheduledScan->organization ? [
                    'id' => $scheduledScan->organization->id,
                    'name' => $scheduledScan->organization->name,
                ] : null,
            ],
            'recentScans' => $recentScans,
        ]);
    }
}
