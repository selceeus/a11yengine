<?php

namespace App\Domain\Agency;

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CreateTeamMember
{
    /**
     * @throws ValidationException
     */
    public function handle(Agency $agency, string $name, string $email, string $password, ?UserRoleEnum $role = null): User
    {
        $this->assertNoExistingMember($agency, $email);

        $user = User::create([
            'agency_id' => $agency->id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        if ($role !== null) {
            $user->roles()->create([
                'role' => $role,
                'agency_id' => $agency->id,
            ]);
        }

        return $user;
    }

    private function assertNoExistingMember(Agency $agency, string $email): void
    {
        $exists = User::withoutGlobalScopes()
            ->where('agency_id', $agency->id)
            ->where('email', $email)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => ['This user is already a member of this agency.'],
            ]);
        }
    }
}
