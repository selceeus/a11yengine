<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduledScanRequest;
use App\Models\Property;
use App\Models\ScheduledScan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ScheduledScanController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreScheduledScanRequest $request, Property $property): JsonResponse
    {
        $this->authorize('create', \App\Models\Scan::class);

        $data = $request->validated();
        $nextRunAt = $this->computeNextRunAt($data);

        $schedule = ScheduledScan::updateOrCreate(
            ['property_id' => $property->id, 'is_active' => true],
            [
                'agency_id' => $property->agency_id,
                'organization_id' => $property->organization_id,
                'type' => $data['type'],
                'frequency' => $data['frequency'] ?? null,
                'scheduled_at' => isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null,
                'next_run_at' => $nextRunAt,
                'last_run_at' => null,
                'is_active' => true,
            ]
        );

        return response()->json(['scheduledScan' => $this->formatSchedule($schedule)]);
    }

    public function update(StoreScheduledScanRequest $request, Property $property, ScheduledScan $scheduledScan): JsonResponse
    {
        $this->authorize('create', \App\Models\Scan::class);

        $data = $request->validated();
        $nextRunAt = $this->computeNextRunAt($data);

        $scheduledScan->update([
            'type' => $data['type'],
            'frequency' => $data['frequency'] ?? null,
            'scheduled_at' => isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null,
            'next_run_at' => $nextRunAt,
            'last_run_at' => null,
            'is_active' => true,
        ]);

        return response()->json(['scheduledScan' => $this->formatSchedule($scheduledScan->fresh())]);
    }

    private function computeNextRunAt(array $data): Carbon
    {
        if ($data['type'] === 'once') {
            return Carbon::parse($data['scheduled_at']);
        }

        return match ($data['frequency']) {
            'daily' => Carbon::now()->addDay(),
            'weekly' => Carbon::now()->addWeek(),
            'monthly' => Carbon::now()->addMonth(),
            'quarterly' => Carbon::now()->addMonths(3),
            default => Carbon::now()->addDay(),
        };
    }

    private function formatSchedule(ScheduledScan $schedule): array
    {
        return [
            'id' => $schedule->id,
            'type' => $schedule->type,
            'frequency' => $schedule->frequency,
            'scheduled_at' => $schedule->scheduled_at?->toIso8601String(),
            'next_run_at' => $schedule->next_run_at->toIso8601String(),
        ];
    }
}
