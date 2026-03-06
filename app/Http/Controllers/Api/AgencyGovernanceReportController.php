<?php

namespace App\Http\Controllers\Api;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\ScanStatus;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyRiskSnapshot;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Scan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyGovernanceReportController extends Controller
{
    public function __invoke(Request $request, Agency $agency): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $agency->id, 403);

        // ── Issues ────────────────────────────────────────────────────────────
        $issueBase = Issue::withoutGlobalScopes()
            ->where('agency_id', $agency->id)
            ->where('status', IssueStatus::Open);

        $openIssues = (clone $issueBase)->count();

        $totalRiskScore = (int) (clone $issueBase)
            ->selectRaw('SUM(risk_weight * occurrence_count) as total')
            ->value('total');

        /** @var array<string, int> $severityCounts */
        $severityCounts = (clone $issueBase)
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $severityDistribution = [
            'critical' => (int) ($severityCounts[IssueSeverity::Critical->value] ?? 0),
            'high' => (int) ($severityCounts[IssueSeverity::High->value] ?? 0),
            'medium' => (int) ($severityCounts[IssueSeverity::Medium->value] ?? 0),
            'low' => (int) ($severityCounts[IssueSeverity::Low->value] ?? 0),
        ];

        // ── Scans ─────────────────────────────────────────────────────────────
        $scanBase = Scan::withoutGlobalScopes()->where('agency_id', $agency->id);

        $totalScans = (clone $scanBase)->count();

        $scansLast30Days = (clone $scanBase)
            ->where('status', ScanStatus::Completed)
            ->where('completed_at', '>=', now()->subDays(30))
            ->count();

        $totalViolations = (int) (clone $scanBase)
            ->where('status', ScanStatus::Completed)
            ->sum('total_violations');

        // ── Risk snapshots ────────────────────────────────────────────────────
        $recentScores = AgencyRiskSnapshot::query()
            ->where('agency_id', $agency->id)
            ->orderByDesc('snapshot_date')
            ->limit(2)
            ->pluck('risk_score');

        $riskDelta = $recentScores->count() >= 2
            ? $recentScores->first() - $recentScores->last()
            : null;

        // ── Organizations ─────────────────────────────────────────────────────
        $organizationsCount = Organization::withoutGlobalScopes()
            ->where('agency_id', $agency->id)
            ->count();

        return response()->json([
            'agency_id' => $agency->id,
            'agency_name' => $agency->name,
            'total_risk_score' => $totalRiskScore,
            'risk_delta' => $riskDelta,
            'open_issues' => $openIssues,
            'severity_distribution' => $severityDistribution,
            'total_scans' => $totalScans,
            'scans_last_30_days' => $scansLast30Days,
            'total_violations' => $totalViolations,
            'organizations_count' => $organizationsCount,
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
