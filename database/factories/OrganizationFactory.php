<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array{agency_id:int, name:string, domain:?string, status:string}
     */
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'name' => fake()->company(),
            'domain' => fake()->optional()->domainName(),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => 'inactive',
        ]);
    }

    public function withoutDomain(): static
    {
        return $this->state(fn (): array => [
            'domain' => null,
        ]);
    }
}
