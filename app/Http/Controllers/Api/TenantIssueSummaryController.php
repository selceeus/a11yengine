<?php

namespace App\Http\Controllers\Api;

use App\Enums\IssueSeverity;
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
            ->whereIn('status', array_map(fn (IssueStatus $s) => $s->value, IssueStatus::activeStatuses()))
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        return response()->json([
            'critical' => (int) ($counts[IssueSeverity::Critical->value] ?? 0),
            'high' => (int) ($counts[IssueSeverity::High->value] ?? 0),
            'medium' => (int) ($counts[IssueSeverity::Medium->value] ?? 0),
            'low' => (int) ($counts[IssueSeverity::Low->value] ?? 0),
            'total' => array_sum($counts),
            'generated_at' => now()->toISOString(),
        ]);
    }
}
