<?php

namespace Database\Factories;

use App\Enums\UserRole as UserRoleEnum;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Attach a role to the user after creation.
     */
    public function withRole(UserRoleEnum $role, ?int $agencyId = null, ?int $orgId = null, ?int $propertyId = null): static
    {
        return $this->afterCreating(function (\App\Models\User $user) use ($role, $agencyId, $orgId, $propertyId): void {
            UserRole::create([
                'user_id' => $user->id,
                'role' => $role,
                'agency_id' => $agencyId,
                'organization_id' => $orgId,
                'property_id' => $propertyId,
            ]);
        });
    }
}
