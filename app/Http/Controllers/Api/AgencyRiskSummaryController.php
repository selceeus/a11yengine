<?php

namespace App\Http\Controllers\Api;

use App\Domain\Risk\GetOrganizationRiskSummary;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyRiskSnapshot;
use Illuminate\Http\JsonResponse;

class AgencyRiskSummaryController extends Controller
{
    public function __invoke(Agency $agency): JsonResponse
    {
        $latestSnapshot = AgencyRiskSnapshot::query()
            ->where('agency_id', $agency->id)
            ->orderByDesc('snapshot_date')
            ->first(['risk_score', 'open_issue_count', 'snapshot_date']);

        return response()->json([
            'agency' => [
                'id' => $agency->id,
                'name' => $agency->name,
                'slug' => $agency->slug,
            ],
            'risk_score' => $latestSnapshot?->risk_score,
            'open_issue_count' => $latestSnapshot?->open_issue_count,
            'snapshot_date' => $latestSnapshot?->snapshot_date?->toDateString(),
            'generated_at' => now()->toISOString(),
        ]);
    }
}
