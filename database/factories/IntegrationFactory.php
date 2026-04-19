<?php

namespace Database\Factories;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationStatus;
use App\Models\Agency;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'property_id' => null,
            'provider' => IntegrationProvider::Jira,
            'name' => fake()->words(3, true),
            'credentials' => [
                'email' => fake()->safeEmail(),
                'api_token' => fake()->sha256(),
                'base_url' => 'https://example.atlassian.net',
                'project_key' => 'A11Y',
            ],
            'settings' => null,
            'status' => IntegrationStatus::Active,
            'error_message' => null,
            'last_synced_at' => null,
        ];
    }
}
