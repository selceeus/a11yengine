<?php

namespace App\Policies;

use App\Models\Audit;
use App\Models\User;

class AuditPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Audit $audit): bool
    {
        return $user->agency_id === $audit->agency_id || $user->isSuperUser();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Audit $audit): bool
    {
        return $user->agency_id === $audit->agency_id || $user->isSuperUser();
    }
}
