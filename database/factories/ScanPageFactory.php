<?php

namespace Database\Factories;

use App\Enums\ScanPageStatus;
use App\Models\Agency;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScanPageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'scan_id' => Scan::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
            ]),
            'url' => fake()->url(),
            'violations_count' => 0,
            'status' => ScanPageStatus::Pending,
        ];
    }

    public function scanned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScanPageStatus::Scanned,
            'violations_count' => fake()->numberBetween(0, 50),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScanPageStatus::Failed,
        ]);
    }
}
