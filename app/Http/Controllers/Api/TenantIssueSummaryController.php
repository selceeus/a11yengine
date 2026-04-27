<?php

namespace App\Http\Controllers\Api;

use App\Domain\Issues\IssueSeveritySummary;
use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;

class TenantIssueSummaryController extends Controller
{
    public function __invoke(Agency $agency): JsonResponse
    {
        /** @var array<string, int> $counts */
        $counts = Issue::withoutGlobalScopes()
            ->where('agency_id', $agency->id)
            ->whereIn('status', IssueStatus::activeStatusValues())
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        return response()->json(IssueSeveritySummary::fromCounts($counts));
    }
}
