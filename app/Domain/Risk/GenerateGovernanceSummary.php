<?php

namespace App\Domain\Risk;

use App\Models\Organization;
use App\Models\RiskSnapshot;
use Illuminate\Support\Arr;

class GenerateGovernanceSummary
{
    public function __construct(
        private readonly GenerateRiskBreakdown $breakdown,
        private readonly GenerateUserImpactReport $userImpact,
    ) {}

    /**
     * @return array{
     *     organization_id: int,
     *     organization_name: string,
     *     total_risk_score: int,
     *     risk_delta: int|null,
     *     open_issues: int,
     *     severity_distribution: array<string, array{count: int, risk_contribution: int}>,
     *     aging_distribution: array{under_30_days: int, 30_to_60_days: int, over_60_days: int},
     *     estimated_user_impact_score: int,
     *     impact_distribution: array{high_impact: int, moderate_impact: int, low_impact: int},
     *     affected_wcag_categories: array{perceivable: int, operable: int, understandable: int, robust: int},
     *     assistive_technology_risk: array{screen_reader: int, keyboard_navigation: int, low_vision: int},
     *     generated_at: string
     * }
     */
    public function handle(Organization $organization): array
    {
        $breakdownData = $this->breakdown->handle($organization);
        $impactData = $this->userImpact->handle($organization);

        $snapshots = RiskSnapshot::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('created_at')
            ->limit(2)
            ->pluck('total_risk_score');

        $riskDelta = $snapshots->count() >= 2
            ? $snapshots->first() - $snapshots->last()
            : null;

        return [
            ...Arr::only($breakdownData, [
                'organization_id',
                'organization_name',
                'total_risk_score',
                'open_issues',
                'severity_distribution',
                'aging_distribution',
                'generated_at',
            ]),
            'risk_delta' => $riskDelta,
            ...Arr::only($impactData, [
                'estimated_user_impact_score',
                'impact_distribution',
                'affected_wcag_categories',
                'assistive_technology_risk',
            ]),
        ];
    }
}
