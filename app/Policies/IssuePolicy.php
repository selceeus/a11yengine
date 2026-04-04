<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;

class IssuePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Issue $issue): bool
    {
        return $user->agency_id === $issue->agency_id;
    }

    public function update(User $user, Issue $issue): bool
    {
        return $user->canEditProperty($issue->property_id);
    }

    public function delete(User $user, Issue $issue): bool
    {
        return $user->canManageProperty($issue->property_id);
    }
}
