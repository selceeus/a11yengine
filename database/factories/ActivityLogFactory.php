<?php

namespace Database\Factories;

use App\Enums\ActivityLogEvent;
use App\Models\ActivityLog;
use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'user_id' => null,
            'actor_type' => 'user',
            'actor_label' => fake()->name(),
            'event' => ActivityLogEvent::UserLogin,
            'subject_type' => null,
            'subject_id' => null,
            'subject_label' => null,
            'metadata' => null,
            'ip_address' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
