<?php

namespace Database\Factories;

use App\Models\ScanJourney;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScanJourneyStep>
 */
class ScanJourneyStepFactory extends Factory
{
    public function definition(): array
    {
        return [
            'scan_journey_id' => ScanJourney::factory(),
            'position' => fake()->numberBetween(0, 9),
            'label' => fake()->words(2, true),
            'url' => fake()->url(),
        ];
    }
}
