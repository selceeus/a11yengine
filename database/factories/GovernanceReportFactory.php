<?php

namespace Database\Factories;

use App\Enums\GovernanceReportStatus;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class GovernanceReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'organization_id' => Organization::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
            ]),
            'property_id' => Property::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
                'organization_id' => $attributes['organization_id'],
            ]),
            'report_scope' => 'property',
            'period_from' => now()->subDays(7)->toDateString(),
            'period_to' => now()->toDateString(),
            'status' => GovernanceReportStatus::Pending,
            'is_scheduled' => false,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GovernanceReportStatus::Completed,
            'generated_at' => now(),
            'executive_narrative' => 'The website has shown moderate improvement over the reporting period. Critical issues related to missing alt text have been reduced by 40%, though serious form labelling issues remain unaddressed across key transactional pages. Immediate attention is recommended to the checkout flow where three high-severity issues persist.',
            'summary_cards' => [
                ['title' => 'Overall Risk Score', 'value' => 62, 'delta' => 8, 'trend' => 'down', 'unit' => '/100'],
                ['title' => 'Open Issues', 'value' => 47, 'delta' => -12, 'trend' => 'down', 'unit' => null],
                ['title' => 'Resolved This Period', 'value' => 15, 'delta' => 5, 'trend' => 'up', 'unit' => null],
                ['title' => 'WCAG AA Compliance', 'value' => 71, 'delta' => 4, 'trend' => 'up', 'unit' => '%'],
            ],
            'risk_trend' => [
                ['date' => now()->subDays(6)->toDateString(), 'risk_score' => 70, 'open_issues' => 59],
                ['date' => now()->subDays(5)->toDateString(), 'risk_score' => 68, 'open_issues' => 57],
                ['date' => now()->subDays(4)->toDateString(), 'risk_score' => 67, 'open_issues' => 55],
                ['date' => now()->subDays(3)->toDateString(), 'risk_score' => 65, 'open_issues' => 52],
                ['date' => now()->subDays(2)->toDateString(), 'risk_score' => 64, 'open_issues' => 50],
                ['date' => now()->subDays(1)->toDateString(), 'risk_score' => 63, 'open_issues' => 48],
                ['date' => now()->toDateString(), 'risk_score' => 62, 'open_issues' => 47],
            ],
            'severity_breakdown' => [
                'critical' => ['open' => 8, 'resolved' => 3, 'ignored' => 1],
                'serious' => ['open' => 21, 'resolved' => 9, 'ignored' => 2],
                'moderate' => ['open' => 14, 'resolved' => 2, 'ignored' => 0],
                'minor' => ['open' => 4, 'resolved' => 1, 'ignored' => 0],
            ],
            'remediation_progress' => [
                'critical' => ['total' => 12, 'resolved' => 3, 'rate' => 25],
                'serious' => ['total' => 32, 'resolved' => 9, 'rate' => 28],
                'moderate' => ['total' => 16, 'resolved' => 2, 'rate' => 13],
                'minor' => ['total' => 5, 'resolved' => 1, 'rate' => 20],
            ],
            'compliance_status' => [
                'wcag_a' => ['pass' => 18, 'fail' => 3, 'partial' => 2],
                'wcag_aa' => ['pass' => 24, 'fail' => 7, 'partial' => 4],
                'wcag_aaa' => ['pass' => 9, 'fail' => 14, 'partial' => 6],
            ],
            'recommendations' => [
                [
                    'priority' => 'high',
                    'title' => 'Fix missing form labels on checkout flow',
                    'rationale' => 'Three critical WCAG 1.3.1 violations on the checkout page block screen reader users from completing purchases, directly impacting conversion for users with disabilities.',
                    'category' => 'form_accessibility',
                    'action' => 'Add explicit <label> elements or aria-label attributes to all form inputs on /checkout, /checkout/payment, and /checkout/review.',
                    'due_by_quarter' => 'Q2 2026',
                    'source_refs' => [
                        ['type' => 'issue', 'id' => 1, 'label' => 'Missing form label — /checkout', 'url' => '/issues/1'],
                    ],
                ],
                [
                    'priority' => 'high',
                    'title' => 'Add alt text to all product images',
                    'rationale' => 'Eight images on product listing pages lack alt attributes entirely, violating WCAG 1.1.1. These pages account for 60% of site traffic.',
                    'category' => 'image_accessibility',
                    'action' => 'Audit all <img> elements in the product catalogue and add meaningful alt text or empty alt="" for decorative images.',
                    'due_by_quarter' => 'Q2 2026',
                    'source_refs' => [
                        ['type' => 'issue', 'id' => 2, 'label' => 'Missing alt text — /products', 'url' => '/issues/2'],
                    ],
                ],
                [
                    'priority' => 'medium',
                    'title' => 'Resolve ambiguous link text site-wide',
                    'rationale' => 'Twelve instances of "click here" and "read more" link text exist across the site. These fail WCAG 2.4.4 and disproportionately impact screen reader users who navigate by link lists.',
                    'category' => 'link_accessibility',
                    'action' => 'Replace generic link text with descriptive alternatives. Consider a content style guide update to prevent recurrence.',
                    'due_by_quarter' => 'Q3 2026',
                    'source_refs' => [],
                ],
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GovernanceReportStatus::Failed,
            'error_message' => 'AI provider returned an invalid response after 2 attempts.',
        ]);
    }

    public function agencyScope(): static
    {
        return $this->state(fn (array $attributes): array => [
            'report_scope' => 'agency',
            'property_id' => null,
        ]);
    }
}
