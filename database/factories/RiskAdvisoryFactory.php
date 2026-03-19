<?php

namespace Database\Factories;

use App\Enums\RiskAdvisoryStatus;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiskAdvisoryFactory extends Factory
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
            'status' => RiskAdvisoryStatus::Pending,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RiskAdvisoryStatus::Completed,
            'total_recommendations' => 3,
            'issues_analyzed' => 42,
            'generated_at' => now(),
            'priorities' => [
                [
                    'rank' => 1,
                    'issue_id' => 1,
                    'title' => 'Images Missing Alt Text',
                    'rule_key' => 'image-alt',
                    'severity' => 'critical',
                    'risk_reduction_score' => 88,
                    'ease_of_remediation' => 'easy',
                    'user_impact' => 'high',
                    'compliance_importance' => 'high',
                    'affected_pages' => 15,
                    'affected_page_urls' => ['/products', '/products/1', '/about'],
                    'quick_win' => true,
                    'rationale' => 'High-traffic pages are missing alt text on product images, causing screen reader failures for a large number of users.',
                ],
                [
                    'rank' => 2,
                    'issue_id' => 2,
                    'title' => 'Insufficient Colour Contrast',
                    'rule_key' => 'color-contrast',
                    'severity' => 'serious',
                    'risk_reduction_score' => 72,
                    'ease_of_remediation' => 'moderate',
                    'user_impact' => 'high',
                    'compliance_importance' => 'high',
                    'affected_pages' => 22,
                    'affected_page_urls' => ['/products', '/checkout', '/account'],
                    'quick_win' => false,
                    'rationale' => 'Button text contrast ratio is below 4.5:1 across the site. A single CSS variable update resolves all instances.',
                ],
                [
                    'rank' => 3,
                    'issue_id' => 3,
                    'title' => 'Form Inputs Missing Labels',
                    'rule_key' => 'label',
                    'severity' => 'critical',
                    'risk_reduction_score' => 65,
                    'ease_of_remediation' => 'easy',
                    'user_impact' => 'high',
                    'compliance_importance' => 'high',
                    'affected_pages' => 8,
                    'affected_page_urls' => ['/checkout', '/account/settings'],
                    'quick_win' => true,
                    'rationale' => 'Contact and checkout forms lack associated label elements, breaking keyboard navigation for assistive technology users.',
                ],
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RiskAdvisoryStatus::Failed,
            'error_message' => 'AI provider did not return valid JSON.',
        ]);
    }
}
