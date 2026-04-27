<?php

namespace App\Domain\Risk;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Scan;
use Illuminate\Support\Facades\Date;

class GetOrganizationRiskSummary
{
    public function __construct(private readonly CalculateOrganizationRiskScore $calculator) {}

    /**
     * @return array{
     *     total_risk_score: int,
     *     open_issues: int,
     *     by_severity: array<string, int>,
     *     aging_buckets: array{under_30_days: int, 30_to_60_days: int, over_60_days: int},
     *     avg_days_to_resolution: float|null,
     *     resolved_last_30_days: int,
     *     net_issue_delta_per_scan: float
     * }
     */
    public function handle(Organization|int $organization): array
    {
        $organizationId = $organization instanceof Organization
            ? $organization->id
            : $organization;

        $now = Date::now();

        $base = Issue::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', IssueStatus::activeStatusValues());

        $totalRiskScore = $this->calculator->handle($organizationId);

        $openIssues = (clone $base)->count();

        $severityCounts = (clone $base)
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity');

        $bySeverity = collect(IssueSeverity::cases())
            ->mapWithKeys(fn (IssueSeverity $s): array => [$s->value => (int) ($severityCounts[$s->value] ?? 0)])
            ->all();

        $agingBuckets = [
            'under_30_days' => (clone $base)
                ->where('first_detected_at', '>=', $now->copy()->subDays(30))
                ->count(),
            '30_to_60_days' => (clone $base)
                ->whereBetween('first_detected_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])
                ->count(),
            'over_60_days' => (clone $base)
                ->where('first_detected_at', '<', $now->copy()->subDays(60))
                ->count(),
        ];

        $avgDaysToResolution = Issue::query()
            ->where('organization_id', $organizationId)
            ->where('status', IssueStatus::Resolved)
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - first_detected_at)) / 86400) as avg_days')
            ->value('avg_days');

        $resolvedLast30Days = Issue::query()
            ->where('organization_id', $organizationId)
            ->where('status', IssueStatus::Resolved)
            ->where('resolved_at', '>=', $now->copy()->subDays(30))
            ->count();

        $totalIssues = Issue::query()
            ->where('organization_id', $organizationId)
            ->count();

        $totalResolved = Issue::query()
            ->where('organization_id', $organizationId)
            ->where('status', IssueStatus::Resolved)
            ->count();

        $scanCount = Scan::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->count();

        $netIssueDeltaPerScan = $scanCount > 0
            ? round(($totalIssues - $totalResolved) / $scanCount, 2)
            : 0.0;

        return [
            'total_risk_score' => $totalRiskScore,
            'open_issues' => $openIssues,
            'by_severity' => $bySeverity,
            'aging_buckets' => $agingBuckets,
            'avg_days_to_resolution' => $avgDaysToResolution !== null ? round((float) $avgDaysToResolution, 2) : null,
            'resolved_last_30_days' => $resolvedLast30Days,
            'net_issue_delta_per_scan' => $netIssueDeltaPerScan,
        ];
    }
}
