<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RiskSnapshot>
 */
class RiskSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agency_id' => \App\Models\Agency::factory(),
            'organization_id' => \App\Models\Organization::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
            ]),
            'total_risk_score' => fake()->numberBetween(0, 500),
            'open_issue_count' => fake()->numberBetween(0, 50),
            'snapshot_date' => fake()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'created_at' => fake()->dateTimeBetween('-90 days', 'now'),
        ];
    }
}
