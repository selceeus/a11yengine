<?php

namespace Database\Factories;

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
            'status' => 'pending',
            'started_at' => null,
            'completed_at' => null,
            'raw_summary' => null,
        ];
    }
}
