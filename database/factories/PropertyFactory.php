<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        $agencyId = Agency::factory();

        return [
            'agency_id' => $agencyId,
            'organization_id' => Organization::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
            ]),
            'name' => fake()->company(),
            'base_url' => fake()->url(),
            'status' => 'active',
        ];
    }
}
