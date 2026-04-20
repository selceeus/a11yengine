<?php

namespace Database\Factories;

use App\Enums\PdfScanStatus;
use App\Models\Agency;
use App\Models\Property;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PdfDocumentFactory extends Factory
{
    public function definition(): array
    {
        $agencyId = Agency::factory();

        return [
            'agency_id' => $agencyId,
            'scan_id' => Scan::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
            ]),
            'property_id' => Property::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
            ]),
            'url' => fake()->url().'/'.fake()->word().'.pdf',
            'filename' => fake()->word().'.pdf',
            'status' => PdfScanStatus::Pending,
            'violation_count' => 0,
            'error_message' => null,
            'scanned_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PdfScanStatus::Completed,
            'violation_count' => fake()->numberBetween(0, 20),
            'scanned_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PdfScanStatus::Failed,
            'error_message' => 'Failed to download PDF.',
            'scanned_at' => now(),
        ]);
    }
}
