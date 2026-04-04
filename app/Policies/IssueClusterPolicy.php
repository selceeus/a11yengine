<?php

namespace App\Policies;

use App\Models\IssueCluster;
use App\Models\Property;
use App\Models\User;

class IssueClusterPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, IssueCluster $cluster): bool
    {
        return $user->agency_id === $cluster->agency_id;
    }

    public function create(User $user, Property $property): bool
    {
        return $user->canEditProperty($property->id);
    }

    public function delete(User $user, IssueCluster $cluster): bool
    {
        return $user->canManageProperty($cluster->property_id);
    }
}
