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
                'run_time' => $data['run_time'] ?? null,
                'run_day_of_week' => $data['run_day_of_week'] ?? null,
                'run_day_of_month' => $data['run_day_of_month'] ?? null,
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
            'run_time' => $data['run_time'] ?? null,
            'run_day_of_week' => $data['run_day_of_week'] ?? null,
            'run_day_of_month' => $data['run_day_of_month'] ?? null,
            'next_run_at' => $nextRunAt,
            'last_run_at' => null,
            'is_active' => true,
        ]);

        return response()->json(['scheduledScan' => $this->formatSchedule($scheduledScan->fresh())]);
    }

    public function destroy(Property $property, ScheduledScan $scheduledScan): JsonResponse
    {
        $this->authorize('create', \App\Models\Scan::class);

        $scheduledScan->update(['is_active' => false]);

        return response()->json(['success' => true]);
    }

    private function computeNextRunAt(array $data): Carbon
    {
        if ($data['type'] === 'once') {
            return Carbon::parse($data['scheduled_at']);
        }

        $now = Carbon::now();
        [$h, $min] = array_map('intval', explode(':', $data['run_time'] ?? '09:00'));
        $dow = isset($data['run_day_of_week']) ? (int) $data['run_day_of_week'] : 1;
        $dom = isset($data['run_day_of_month']) ? (int) $data['run_day_of_month'] : 1;

        return match ($data['frequency']) {
            'daily' => $this->resolveDaily($now, $h, $min),
            'weekly' => $this->resolveWeekly($now, $h, $min, $dow),
            'monthly' => $this->resolveMonthly($now, $h, $min, $dom),
            'quarterly' => $this->resolveQuarterly($now, $h, $min, $dom),
            default => $this->resolveDaily($now, $h, $min),
        };
    }

    private function resolveDaily(Carbon $now, int $h, int $min): Carbon
    {
        $candidate = $now->copy()->setTime($h, $min, 0);

        return $candidate->isFuture() ? $candidate : $candidate->addDay();
    }

    private function resolveWeekly(Carbon $now, int $h, int $min, int $dow): Carbon
    {
        if ($now->dayOfWeek === $dow) {
            $today = $now->copy()->setTime($h, $min, 0);
            if ($today->isFuture()) {
                return $today;
            }
        }

        return $now->copy()->next($dow)->setTime($h, $min, 0);
    }

    private function resolveMonthly(Carbon $now, int $h, int $min, int $dom): Carbon
    {
        $candidate = $now->copy()->setDay(min($dom, $now->daysInMonth))->setTime($h, $min, 0);
        if ($candidate->isFuture()) {
            return $candidate;
        }

        $next = $now->copy()->addMonthNoOverflow()->startOfMonth();

        return $next->setDay(min($dom, $next->daysInMonth))->setTime($h, $min, 0);
    }

    private function resolveQuarterly(Carbon $now, int $h, int $min, int $dom): Carbon
    {
        $quarterMonths = [1, 4, 7, 10];
        $year = $now->year;

        foreach ([0, 1] as $pass) {
            foreach ($quarterMonths as $qm) {
                if ($pass === 0 && $qm < $now->month) {
                    continue;
                }

                $candidate = Carbon::create($year, $qm, 1, 0, 0, 0);
                $candidate->setDay(min($dom, $candidate->daysInMonth))->setTime($h, $min, 0);

                if ($candidate->isFuture()) {
                    return $candidate;
                }
            }

            $year++;
        }

        return Carbon::now()->addMonths(3)->setTime($h, $min, 0);
    }

    private function formatSchedule(ScheduledScan $schedule): array
    {
        return [
            'id' => $schedule->id,
            'type' => $schedule->type,
            'frequency' => $schedule->frequency,
            'scheduled_at' => $schedule->scheduled_at?->toIso8601String(),
            'next_run_at' => $schedule->next_run_at->toIso8601String(),
            'run_time' => $schedule->run_time,
            'run_day_of_week' => $schedule->run_day_of_week,
            'run_day_of_month' => $schedule->run_day_of_month,
        ];
    }
}
