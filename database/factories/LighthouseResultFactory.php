<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LighthouseResult>
 */
class LighthouseResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'scan_id' => Scan::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
            ]),
            'url' => fake()->url(),
            'performance_score' => fake()->numberBetween(0, 100),
            'accessibility_score' => fake()->numberBetween(0, 100),
            'best_practices_score' => fake()->numberBetween(0, 100),
            'seo_score' => fake()->numberBetween(0, 100),
            'first_contentful_paint' => fake()->randomFloat(2, 500, 5000),
            'largest_contentful_paint' => fake()->randomFloat(2, 1000, 8000),
            'total_blocking_time' => fake()->randomFloat(2, 0, 2000),
            'cumulative_layout_shift' => fake()->randomFloat(4, 0, 0.5),
            'raw_metrics' => null,
        ];
    }
}
