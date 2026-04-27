<?php

namespace App\Domain\Governance;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\ScanStatus;
use App\Models\Agency;
use App\Models\AgencyRiskSnapshot;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Scan;

class AgencyGovernanceSummary
{
    /**
     * Build the full governance summary payload for an agency.
     *
     * @return array<string, mixed>
     */
    public static function forAgency(Agency $agency): array
    {
        // ── Issues ────────────────────────────────────────────────────────────
        $issueBase = Issue::withoutGlobalScopes()
            ->where('agency_id', $agency->id)
            ->whereIn('status', IssueStatus::activeStatusValues());

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

        return [
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
        ];
    }
}
