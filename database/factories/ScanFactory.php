<?php

namespace Database\Factories;

use App\Enums\ScanStatus;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScanFactory extends Factory
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
            'status' => ScanStatus::Pending,
            'pages_scanned' => null,
            'total_violations' => null,
            'raw_output_path' => null,
            'started_at' => null,
            'completed_at' => null,
            'raw_summary' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScanStatus::Completed,
            'pages_scanned' => fake()->numberBetween(1, 500),
            'total_violations' => fake()->numberBetween(0, 200),
            'raw_output_path' => 'scans/'.fake()->uuid().'.json',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScanStatus::Running,
            'started_at' => now()->subMinutes(1),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScanStatus::Failed,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);
    }
}
