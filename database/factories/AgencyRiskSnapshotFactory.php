<?php

namespace Database\Factories;

use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AgencyRiskSnapshot>
 */
class AgencyRiskSnapshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'risk_score' => fake()->numberBetween(0, 500),
            'open_issue_count' => fake()->numberBetween(0, 50),
            'snapshot_date' => fake()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'created_at' => now(),
        ];
    }
}
