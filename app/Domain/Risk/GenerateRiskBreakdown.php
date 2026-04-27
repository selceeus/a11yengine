<?php

namespace App\Domain\Risk;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Organization;
use Illuminate\Support\Facades\Date;

class GenerateRiskBreakdown
{
    /** @var array<string, IssueSeverity> */
    private const SEVERITY_MAP = [
        'critical' => IssueSeverity::Critical,
        'serious' => IssueSeverity::High,
        'moderate' => IssueSeverity::Medium,
        'minor' => IssueSeverity::Low,
    ];

    public function __construct(private readonly CalculateOrganizationRiskScore $calculator) {}

    /**
     * @return array{
     *     organization_id: int,
     *     organization_name: string,
     *     total_risk_score: int,
     *     open_issues: int,
     *     severity_distribution: array<string, array{count: int, risk_contribution: int}>,
     *     aging_distribution: array{under_30_days: int, 30_to_60_days: int, over_60_days: int},
     *     highest_risk_rules: array<int, array{rule_key: string, issue_count: int, risk_contribution: int}>,
     *     generated_at: string
     * }
     */
    public function handle(Organization $organization): array
    {
        $now = Date::now();

        $base = Issue::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', IssueStatus::activeStatusValues());

        $totalRiskScore = $this->calculator->handle($organization);

        $openIssues = (clone $base)->count();

        $severityRows = (clone $base)
            ->selectRaw('severity, COUNT(*) as count, SUM(risk_weight * occurrence_count) as risk_contribution')
            ->groupBy('severity')
            ->get()
            ->keyBy('severity');

        $severityDistribution = collect(self::SEVERITY_MAP)
            ->mapWithKeys(function (IssueSeverity $severity, string $key) use ($severityRows): array {
                $row = $severityRows->get($severity->value);

                return [$key => [
                    'count' => (int) ($row?->count ?? 0),
                    'risk_contribution' => (int) ($row?->risk_contribution ?? 0),
                ]];
            })
            ->all();

        $agingDistribution = [
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

        $highestRiskRules = (clone $base)
            ->selectRaw('rule_key, COUNT(*) as issue_count, SUM(risk_weight * occurrence_count) as risk_contribution')
            ->groupBy('rule_key')
            ->orderByRaw('SUM(risk_weight * occurrence_count) DESC')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'rule_key' => $row->rule_key,
                'issue_count' => (int) $row->issue_count,
                'risk_contribution' => (int) $row->risk_contribution,
            ])
            ->values()
            ->all();

        return [
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'total_risk_score' => $totalRiskScore,
            'open_issues' => $openIssues,
            'severity_distribution' => $severityDistribution,
            'aging_distribution' => $agingDistribution,
            'highest_risk_rules' => $highestRiskRules,
            'generated_at' => $now->toIso8601String(),
        ];
    }
}
