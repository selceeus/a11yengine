<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NotificationWebhookRoute>
 */
class NotificationWebhookRouteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agency_id' => \App\Models\Agency::factory(),
            'category' => fake()->randomElement(\App\Enums\NotificationEmailCategory::cases())->value,
            'platform' => fake()->randomElement(\App\Enums\MessagingPlatform::cases())->value,
            'webhook_url' => 'https://hooks.example.com/services/'.fake()->bothify('T#########/B#########/??????????????????????????'),
            'label' => fake()->optional()->words(2, true),
        ];
    }
}
