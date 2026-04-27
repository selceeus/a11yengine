<?php

namespace App\Http\Controllers\Api;

use App\Domain\Risk\RiskTrendSpine;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\RiskSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyOrgRiskTrendsController extends Controller
{
    public function __invoke(Request $request, Agency $agency): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $agency->id, 403);

        $days = (int) $request->query('days', 30);

        RiskTrendSpine::validateDays($days);

        $windowStart = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        // Resolve visible organization IDs scoped to this agency
        $orgIds = Organization::withoutGlobalScopes()
            ->where('agency_id', $agency->id)
            ->pluck('name', 'id');

        if ($orgIds->isEmpty()) {
            return response()->json(['organizations' => [], 'days' => [], 'generated_at' => now()->toISOString()]);
        }

        $snapshots = RiskSnapshot::query()
            ->whereIn('organization_id', $orgIds->keys())
            ->where('snapshot_date', '>=', $windowStart->toDateString())
            ->orderBy('snapshot_date')
            ->get(['organization_id', 'snapshot_date', 'total_risk_score', 'open_issue_count']);

        // Build full date spine
        $dateSpine = RiskTrendSpine::buildDateSpine($windowStart, $days);

        // Index snapshots by [org_id][date]
        $indexed = [];
        foreach ($snapshots as $snap) {
            $indexed[$snap->organization_id][$snap->snapshot_date->toDateString()] = [
                'risk_score' => $snap->total_risk_score,
                'open_issues' => $snap->open_issue_count,
            ];
        }

        // Build per-org series with zero-filled gaps
        $organizations = [];
        foreach ($orgIds as $id => $name) {
            $series = RiskTrendSpine::buildSeries($indexed[$id] ?? [], $dateSpine);
            $organizations[] = [
                'id' => $id,
                'name' => $name,
                'series' => $series,
            ];
        }

        return response()->json([
            'organizations' => $organizations,
            'days' => $dateSpine,
            'generated_at' => now()->toISOString(),
        ]);
    }
}
