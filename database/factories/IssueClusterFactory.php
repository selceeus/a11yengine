<?php

namespace Database\Factories;

use App\Enums\ClusterStatus;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class IssueClusterFactory extends Factory
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
            'status' => ClusterStatus::Pending,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ClusterStatus::Completed,
            'total_clusters' => 2,
            'open_issues_analyzed' => 10,
            'generated_at' => now(),
            'clusters' => [
                [
                    'cluster_name' => 'Missing Image Alt Text',
                    'common_component' => 'ProductCard template',
                    'recommended_fix' => 'Add descriptive alt attributes to all img elements in the ProductCard component.',
                    'severity' => 'critical',
                    'priority' => 'high',
                    'issue_ids' => [1, 2, 3],
                    'wcag_categories' => ['1.1.1'],
                    'affected_pages' => 5,
                    'ai_notes' => 'Systemic template issue. A single component fix resolves all instances.',
                ],
                [
                    'cluster_name' => 'Insufficient Colour Contrast',
                    'common_component' => 'Button component',
                    'recommended_fix' => 'Update text colour in Button to meet 4.5:1 contrast ratio.',
                    'severity' => 'serious',
                    'priority' => 'high',
                    'issue_ids' => [4, 5],
                    'wcag_categories' => ['1.4.3'],
                    'affected_pages' => 8,
                    'ai_notes' => 'Global button style issue. CSS variable change resolves all instances.',
                ],
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ClusterStatus::Failed,
            'error_message' => 'AI provider did not return valid JSON.',
        ]);
    }
}
