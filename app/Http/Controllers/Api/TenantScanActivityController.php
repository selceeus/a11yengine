<?php

namespace App\Http\Controllers\Api;

use App\Domain\Scans\ScanActivitySummary;
use App\Enums\ScanStatus;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Scan;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class TenantScanActivityController extends Controller
{
    public function __invoke(Agency $agency): JsonResponse
    {
        $windowStart = CarbonImmutable::now()->subDays(29)->startOfDay();

        /** @var array<string, array{scans: int, violations: int}> $byDate */
        $byDate = Scan::withoutGlobalScopes()
            ->where('agency_id', $agency->id)
            ->where('status', ScanStatus::Completed)
            ->where('completed_at', '>=', $windowStart)
            ->selectRaw('completed_at::date as day, COUNT(*) as scan_count, SUM(total_violations) as total_violations')
            ->groupByRaw('completed_at::date')
            ->orderByRaw('completed_at::date')
            ->get()
            ->keyBy('day')
            ->map(fn ($row) => [
                'scans' => (int) $row->scan_count,
                'violations' => (int) ($row->total_violations ?? 0),
            ])
            ->toArray();

        // Fill in every day in the window with zeros so the chart always gets 30 points
        $days = ScanActivitySummary::buildDaySpine($byDate, $windowStart);

        return response()->json([
            'days' => $days,
            'generated_at' => now()->toISOString(),
        ]);
    }
}
