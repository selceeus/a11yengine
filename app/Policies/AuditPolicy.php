<?php

namespace App\Policies;

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Audit;
use App\Models\Property;
use App\Models\User;

class AuditPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Audit $audit): bool
    {
        return $user->agency_id === $audit->agency_id;
    }

    public function create(User $user, ?Property $property = null): bool
    {
        if ($property !== null) {
            return $user->canEditProperty($property->id);
        }

        return $user->canManageAgency($user->agency_id)
            || $user->roles()->whereIn('role', [
                UserRoleEnum::OrgAdmin->value,
                UserRoleEnum::PropAdmin->value,
                UserRoleEnum::Editor->value,
            ])->exists();
    }

    public function delete(User $user, Audit $audit): bool
    {
        return $user->canManageProperty($audit->property_id);
    }
}
