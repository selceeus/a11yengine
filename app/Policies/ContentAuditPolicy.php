<?php

namespace App\Policies;

use App\Models\ContentAudit;
use App\Models\Property;
use App\Models\User;

class ContentAuditPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ContentAudit $audit): bool
    {
        return $user->agency_id === $audit->agency_id;
    }

    public function create(User $user, Property $property): bool
    {
        return $user->canEditProperty($property->id);
    }

    public function delete(User $user, ContentAudit $audit): bool
    {
        return $user->canManageProperty($audit->property_id);
    }
}
