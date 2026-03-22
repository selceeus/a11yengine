<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
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
        ]);
    }
}
