<?php

namespace Database\Factories;

use App\Enums\UserRole as UserRoleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserRole>
 */
class UserRoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => fake()->randomElement(UserRoleEnum::cases()),
            'agency_id' => null,
            'organization_id' => null,
            'property_id' => null,
            //
        ];
    }
}
