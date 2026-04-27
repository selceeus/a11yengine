<?php

namespace App\Http\Controllers\Api;

use App\Domain\Risk\RiskTrendSpine;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyRiskSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyRiskTrendsController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Property $property): JsonResponse
    {
        $this->authorize('view', $property);

        $days = (int) $request->query('days', 30);

        RiskTrendSpine::validateDays($days);

        $windowStart = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        $snapshots = PropertyRiskSnapshot::query()
            ->where('property_id', $property->id)
            ->where('snapshot_date', '>=', $windowStart->toDateString())
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'risk_score', 'open_issue_count']);

        $dateSpine = RiskTrendSpine::buildDateSpine($windowStart, $days);

        $indexed = [];
        foreach ($snapshots as $snap) {
            $indexed[$snap->snapshot_date->toDateString()] = [
                'risk_score' => $snap->risk_score,
                'open_issues' => $snap->open_issue_count,
            ];
        }

        $series = RiskTrendSpine::buildSeries($indexed, $dateSpine);

        return response()->json([
            'series' => $series,
            'days' => $dateSpine,
            'generated_at' => now()->toISOString(),
        ]);
    }
}
