<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\ScanJourney;
use App\Models\User;

class ScanJourneyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ScanJourney $scanJourney): bool
    {
        return $user->agency_id === $scanJourney->agency_id;
    }

    public function create(User $user, ?Property $property = null): bool
    {
        if ($property !== null) {
            return $user->canEditProperty($property->id);
        }

        return $user->canManageAgency($user->agency_id)
            || $user->roles()->whereIn('role', [
                \App\Enums\UserRole::OrgAdmin->value,
                \App\Enums\UserRole::PropAdmin->value,
                \App\Enums\UserRole::Editor->value,
            ])->exists();
    }

    public function update(User $user, ScanJourney $scanJourney): bool
    {
        return $user->canEditProperty($scanJourney->property_id);
    }

    public function delete(User $user, ScanJourney $scanJourney): bool
    {
        return $user->canManageProperty($scanJourney->property_id);
    }
}
