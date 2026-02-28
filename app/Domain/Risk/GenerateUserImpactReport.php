<?php

namespace App\Domain\Risk;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Organization;
use Illuminate\Support\Facades\Date;

class GenerateUserImpactReport
{
    /**
     * WCAG category determined by the principle digit in the rule key (wcag-{principle}.x.x).
     *
     * @var array<string, string>
     */
    private const WCAG_CATEGORY_PREFIXES = [
        'perceivable' => 'wcag-1.',
        'operable' => 'wcag-2.',
        'understandable' => 'wcag-3.',
        'robust' => 'wcag-4.',
    ];

    /**
     * Assistive technology risk is determined by the criterion prefix (wcag-{principle}.{guideline}.%).
     *
     * Screen reader: non-text content (1.1), adaptable (1.3), compatible (4.1)
     * Keyboard navigation: keyboard accessible (2.1), navigable (2.4)
     * Low vision: distinguishable (1.4)
     *
     * @var array<string, list<string>>
     */
    private const AT_RISK_PREFIXES = [
        'screen_reader' => ['wcag-1.1.', 'wcag-1.3.', 'wcag-4.1.'],
        'keyboard_navigation' => ['wcag-2.1.', 'wcag-2.4.'],
        'low_vision' => ['wcag-1.4.'],
    ];

    /**
     * @return array{
     *     organization_id: int,
     *     total_open_issues: int,
     *     estimated_user_impact_score: int,
     *     impact_distribution: array{high_impact: int, moderate_impact: int, low_impact: int},
     *     affected_wcag_categories: array{perceivable: int, operable: int, understandable: int, robust: int},
     *     assistive_technology_risk: array{screen_reader: int, keyboard_navigation: int, low_vision: int},
     *     generated_at: string
     * }
     */
    public function handle(Organization $organization): array
    {
        $base = Issue::query()
            ->where('organization_id', $organization->id)
            ->where('status', IssueStatus::Open);

        $totalOpenIssues = (clone $base)->count();

        $highImpact = (clone $base)
            ->whereIn('severity', [IssueSeverity::Critical->value, IssueSeverity::High->value])
            ->count();

        $moderateImpact = (clone $base)
            ->where('severity', IssueSeverity::Medium->value)
            ->count();

        $lowImpact = (clone $base)
            ->where('severity', IssueSeverity::Low->value)
            ->count();

        $estimatedUserImpactScore = $this->calculateImpactScore($highImpact, $moderateImpact, $lowImpact, $totalOpenIssues);

        $affectedWcagCategories = collect(self::WCAG_CATEGORY_PREFIXES)
            ->mapWithKeys(fn (string $prefix, string $category): array => [
                $category => (clone $base)->where('rule_key', 'like', $prefix.'%')->count(),
            ])
            ->all();

        $assistiveTechnologyRisk = collect(self::AT_RISK_PREFIXES)
            ->mapWithKeys(function (array $prefixes, string $technology) use ($base): array {
                $count = (clone $base)
                    ->where(function ($query) use ($prefixes): void {
                        foreach ($prefixes as $i => $prefix) {
                            if ($i === 0) {
                                $query->where('rule_key', 'like', $prefix.'%');
                            } else {
                                $query->orWhere('rule_key', 'like', $prefix.'%');
                            }
                        }
                    })
                    ->count();

                return [$technology => $count];
            })
            ->all();

        return [
            'organization_id' => $organization->id,
            'total_open_issues' => $totalOpenIssues,
            'estimated_user_impact_score' => $estimatedUserImpactScore,
            'impact_distribution' => [
                'high_impact' => $highImpact,
                'moderate_impact' => $moderateImpact,
                'low_impact' => $lowImpact,
            ],
            'affected_wcag_categories' => $affectedWcagCategories,
            'assistive_technology_risk' => $assistiveTechnologyRisk,
            'generated_at' => Date::now()->toIso8601String(),
        ];
    }

    private function calculateImpactScore(int $highImpact, int $moderateImpact, int $lowImpact, int $totalOpenIssues): int
    {
        if ($totalOpenIssues === 0) {
            return 0;
        }

        $rawScore = ($highImpact * 3) + ($moderateImpact * 2) + $lowImpact;
        $maxPossible = $totalOpenIssues * 3;

        return min(100, (int) round($rawScore / $maxPossible * 100));
    }
}
