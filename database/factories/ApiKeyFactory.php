<?php

namespace Database\Factories;

use App\Enums\ApiKeyScope;
use App\Models\Agency;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition(): array
    {
        $secret = Str::random(40);
        $prefix = 'cbda_';
        $plaintext = $prefix.$secret;

        return [
            'agency_id' => Agency::factory(),
            'created_by' => User::factory(),
            'name' => fake()->words(3, true),
            'key_prefix' => substr($plaintext, 0, 12).'...',
            'token_hash' => hash('sha256', $plaintext),
            'scopes' => [ApiKeyScope::ScansRead->value],
            'last_used_at' => null,
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }
}
