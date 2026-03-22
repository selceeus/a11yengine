<?php

namespace App\Http\Controllers\Api;

use App\Enums\ScanStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgScanActivityController extends Controller
{
    public function __invoke(Request $request, Organization $organization): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $organization->agency_id, 403);

        $windowStart = CarbonImmutable::now()->subDays(29)->startOfDay();

        $propertyIds = Property::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->pluck('id');

        /** @var array<string, array{scans: int, violations: int}> $byDate */
        $byDate = Scan::withoutGlobalScopes()
            ->where('agency_id', $organization->agency_id)
            ->whereIn('property_id', $propertyIds)
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

        $days = [];
        for ($i = 0; $i < 30; $i++) {
            $date = $windowStart->addDays($i)->toDateString();
            $days[] = [
                'date' => $date,
                'scans' => $byDate[$date]['scans'] ?? 0,
                'violations' => $byDate[$date]['violations'] ?? 0,
            ];
        }

        return response()->json([
            'days' => $days,
            'generated_at' => now()->toISOString(),
        ]);
    }
}
