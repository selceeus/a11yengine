<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->agency_id === $organization->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->canManageAgency($user->agency_id);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->canManageOrg($organization->id);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->canManageAgency($organization->agency_id);
    }
}
