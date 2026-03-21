<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PropertyFactory extends Factory
{
    public function definition(): array
    {
        $agencyId = Agency::factory();

        $name = fake()->company();

        return [
            'agency_id' => $agencyId,
            'organization_id' => Organization::factory()->state(fn (array $attributes) => [
                'agency_id' => $attributes['agency_id'],
            ]),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'base_url' => fake()->url(),
            'status' => 'active',
        ];
    }
}
