<?php

namespace Database\Factories;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class IssueFactory extends Factory
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
            'rule_key' => 'wcag-'.fake()->numerify('#.#.#'),
            'page_url' => fake()->url(),
            'severity' => fake()->randomElement(IssueSeverity::cases()),
            'tags' => fake()->optional()->randomElements(['wcag2aa', 'wcag143', 'color', 'best-practice'], 2),
            'help_url' => fake()->optional()->url(),
            'status' => IssueStatus::Open,
            'occurrence_count' => fake()->numberBetween(1, 50),
            'risk_weight' => fake()->numberBetween(0, 100),
            'first_detected_at' => fake()->dateTimeBetween('-2 months', '-1 month'),
            'last_detected_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'resolved_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => IssueStatus::Resolved,
            'resolved_at' => fake()->dateTimeBetween(
                $attributes['last_detected_at'] ?? '-1 month',
                'now'
            ),
        ]);
    }
}
