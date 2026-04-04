<?php

namespace App\Policies;

use App\Models\GovernanceReport;
use App\Models\Property;
use App\Models\User;

class GovernanceReportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GovernanceReport $report): bool
    {
        return $user->agency_id === $report->agency_id;
    }

    public function create(User $user, ?Property $property = null): bool
    {
        if ($property !== null) {
            return $user->canEditProperty($property->id);
        }

        return $user->canManageAgency($user->agency_id);
    }

    public function delete(User $user, GovernanceReport $report): bool
    {
        if ($report->property_id !== null) {
            return $user->canManageProperty($report->property_id);
        }

        return $user->canManageAgency($report->agency_id);
    }
}
