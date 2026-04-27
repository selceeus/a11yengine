<?php

namespace App\Http\Controllers\Api;

use App\Domain\Scans\ScanActivitySummary;
use App\Enums\ScanStatus;
use App\Http\Controllers\Controller;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyScanActivityController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Property $property): JsonResponse
    {
        $this->authorize('view', $property);

        $windowStart = CarbonImmutable::now()->subDays(29)->startOfDay();

        /** @var array<string, array{scans: int, violations: int}> $byDate */
        $byDate = $property->scans()
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

        $days = ScanActivitySummary::buildDaySpine($byDate, $windowStart);

        return response()->json([
            'days' => $days,
            'generated_at' => now()->toISOString(),
        ]);
    }
}
