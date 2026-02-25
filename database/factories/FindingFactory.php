<?php

namespace Database\Factories;

use App\Enums\FindingSeverity;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Finding>
 */
class FindingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'scan_id' => Scan::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
            ]),
            'property_id' => Property::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
            ]),
            'rule_key' => 'wcag-'.fake()->numerify('#.#.#'),
            'severity' => fake()->randomElement(FindingSeverity::cases()),
            'element_identifier' => fake()->optional()->domainWord(),
            'page_url' => fake()->url(),
            'message' => fake()->sentence(),
            'detected_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
