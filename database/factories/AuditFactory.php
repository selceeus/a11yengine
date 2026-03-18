<?php

namespace Database\Factories;

use App\Enums\AuditStatus;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditFactory extends Factory
{
    public function definition(): array
    {
        $agencyId = Agency::factory();

        return [
            'agency_id' => $agencyId,
            'organization_id' => Organization::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
            ]),
            'property_id' => Property::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
                'organization_id' => $attributes['organization_id'],
            ]),
            'title' => fake()->sentence(4),
            'status' => AuditStatus::Pending,
            'source_scan_ids' => [],
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AuditStatus::Completed,
            'overall_score' => fake()->numberBetween(40, 100),
            'executive_summary' => fake()->paragraph(),
            'generated_at' => now(),
            'compliance_status' => [
                'wcag_a' => 'pass',
                'wcag_aa' => 'partial',
                'wcag_aaa' => 'fail',
            ],
            'summary_statistics' => [
                'total_issues' => fake()->numberBetween(5, 50),
                'critical_issues' => fake()->numberBetween(0, 10),
                'pages_scanned' => fake()->numberBetween(1, 20),
                'avg_issues_per_page' => fake()->randomFloat(1, 0, 10),
                'most_common_issue' => 'Missing alt text',
            ],
            'top_risks' => [
                ['title' => 'Missing alt text', 'severity' => 'critical', 'count' => 5],
                ['title' => 'Low contrast ratio', 'severity' => 'serious', 'count' => 3],
            ],
            'issue_details' => [
                ['id' => 1, 'title' => 'Missing alt text', 'wcag' => '1.1.1', 'severity' => 'critical'],
            ],
            'remediations' => [
                ['issue_id' => 1, 'priority' => 'high', 'title' => 'Add alt text to images', 'steps' => ['Step 1'], 'code_example' => null],
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AuditStatus::Failed,
            'error_message' => fake()->sentence(),
        ]);
    }
}
