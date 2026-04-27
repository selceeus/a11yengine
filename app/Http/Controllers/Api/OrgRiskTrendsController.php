<?php

namespace App\Http\Controllers\Api;

use App\Domain\Risk\RiskTrendSpine;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\RiskSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgRiskTrendsController extends Controller
{
    public function __invoke(Request $request, Organization $organization): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $organization->agency_id, 403);

        $days = (int) $request->query('days', 30);

        RiskTrendSpine::validateDays($days);

        $windowStart = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        $snapshots = RiskSnapshot::query()
            ->where('organization_id', $organization->id)
            ->where('snapshot_date', '>=', $windowStart->toDateString())
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'total_risk_score', 'open_issue_count']);

        $dateSpine = RiskTrendSpine::buildDateSpine($windowStart, $days);

        $indexed = [];
        foreach ($snapshots as $snap) {
            $indexed[$snap->snapshot_date->toDateString()] = [
                'risk_score' => $snap->total_risk_score,
                'open_issues' => $snap->open_issue_count,
            ];
        }

        $series = RiskTrendSpine::buildSeries($indexed, $dateSpine);

        $orgEntry = [
            'id' => $organization->id,
            'name' => $organization->name,
            'series' => $series,
        ];

        return response()->json([
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'series' => $series,
            'organizations' => [$orgEntry],
            'days' => $dateSpine,
            'generated_at' => now()->toISOString(),
        ]);
    }
}
