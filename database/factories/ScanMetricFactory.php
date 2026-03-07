<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Scan;
use App\Models\ScanPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScanMetric>
 */
class ScanMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'scan_id' => Scan::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
            ]),
            'page_id' => ScanPage::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
                'scan_id' => $attributes['scan_id'],
            ]),
            'metric_name' => fake()->randomElement([
                'accessibility_issue_count',
                'lighthouse_performance',
                'lighthouse_accessibility',
                'lighthouse_best_practices',
                'lighthouse_seo',
                'first_contentful_paint',
                'largest_contentful_paint',
                'total_blocking_time',
                'cumulative_layout_shift',
            ]),
            'metric_value' => fake()->randomFloat(4, 0, 100),
            'metric_source' => fake()->randomElement(['axe', 'lighthouse']),
        ];
    }
}
