<?php

namespace App\Policies;

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Property $property): bool
    {
        return $user->agency_id === $property->agency_id;
    }

    public function create(User $user, ?Organization $organization = null): bool
    {
        if ($organization !== null) {
            return $user->canManageOrg($organization->id);
        }

        return $user->canManageAgency($user->agency_id)
            || $user->roles()->where('role', UserRoleEnum::OrgAdmin->value)->exists();
    }

    public function update(User $user, Property $property): bool
    {
        return $user->canManageProperty($property->id);
    }

    public function delete(User $user, Property $property): bool
    {
        return $user->canManageOrg($property->organization_id);
    }
}
