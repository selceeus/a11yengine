<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\RiskAdvisory;
use App\Models\User;

class RiskAdvisoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RiskAdvisory $advisory): bool
    {
        return $user->agency_id === $advisory->agency_id;
    }

    public function create(User $user, Property $property): bool
    {
        return $user->canEditProperty($property->id);
    }

    public function delete(User $user, RiskAdvisory $advisory): bool
    {
        return $user->canManageProperty($advisory->property_id);
    }
}
