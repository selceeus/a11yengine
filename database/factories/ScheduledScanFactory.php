<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ScheduledScanFactory extends Factory
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
            'type' => 'recurring',
            'frequency' => 'weekly',
            'scheduled_at' => null,
            'run_time' => '09:00',
            'run_day_of_week' => null,
            'run_day_of_month' => null,
            'next_run_at' => Carbon::now()->addWeek(),
            'last_run_at' => null,
            'is_active' => true,
        ];
    }

    public function once(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'once',
            'frequency' => null,
            'scheduled_at' => Carbon::now()->addDay(),
            'next_run_at' => Carbon::now()->addDay(),
        ]);
    }

    public function due(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_run_at' => Carbon::now()->subMinute(),
        ]);
    }
}
