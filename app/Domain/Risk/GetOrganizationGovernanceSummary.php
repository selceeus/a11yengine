<?php

namespace App\Domain\Risk;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\RiskSnapshot;
use App\Models\Scan;
use Illuminate\Support\Facades\Date;

class GetOrganizationGovernanceSummary
{
    public function __construct(private readonly CalculateOrganizationRiskScore $calculator) {}

    /**
     * @return array{
     *     organization_id: int,
     *     risk_score: int,
     *     risk_delta: int|null,
     *     open_issues: int,
     *     new_issues_since_last_scan: int,
     *     resolved_issues_since_last_scan: int,
     *     aging_high_risk_issues: int,
     *     last_scan_at: string|null,
     *     snapshot_at: string
     * }
     */
    public function handle(Organization|int $organization): array
    {
        $organizationId = $organization instanceof Organization
            ? $organization->id
            : $organization;

        $now = Date::now();

        $riskScore = $this->calculator->handle($organizationId);

        // Risk delta vs the previous snapshot
        $snapshots = RiskSnapshot::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('created_at')
            ->limit(2)
            ->pluck('total_risk_score');

        $riskDelta = $snapshots->count() >= 2
            ? $snapshots->first() - $snapshots->last()
            : null;

        $openIssues = Issue::query()
            ->where('organization_id', $organizationId)
            ->where('status', IssueStatus::Open)
            ->count();

        // Last completed scan timestamp
        $lastScan = Scan::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->first();

        $lastScanAt = $lastScan?->completed_at;

        $newIssuesSinceLastScan = $lastScanAt
            ? Issue::query()
                ->where('organization_id', $organizationId)
                ->where('first_detected_at', '>=', $lastScanAt)
                ->count()
            : 0;

        $resolvedIssuesSinceLastScan = $lastScanAt
            ? Issue::query()
                ->where('organization_id', $organizationId)
                ->where('status', IssueStatus::Resolved)
                ->where('resolved_at', '>=', $lastScanAt)
                ->count()
            : 0;

        // Open issues that are high/critical and first detected more than 30 days ago
        $agingHighRiskIssues = Issue::query()
            ->where('organization_id', $organizationId)
            ->where('status', IssueStatus::Open)
            ->whereIn('severity', [IssueSeverity::High->value, IssueSeverity::Critical->value])
            ->where('first_detected_at', '<', $now->copy()->subDays(30))
            ->count();

        return [
            'organization_id' => $organizationId,
            'risk_score' => $riskScore,
            'risk_delta' => $riskDelta,
            'open_issues' => $openIssues,
            'new_issues_since_last_scan' => $newIssuesSinceLastScan,
            'resolved_issues_since_last_scan' => $resolvedIssuesSinceLastScan,
            'aging_high_risk_issues' => $agingHighRiskIssues,
            'last_scan_at' => $lastScanAt?->toIso8601String(),
            'snapshot_at' => $now->toIso8601String(),
        ];
    }
}
