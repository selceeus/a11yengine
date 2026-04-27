<?php

namespace App\Domain\Risk;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Organization;

class CalculateOrganizationRiskScore
{
    public function handle(Organization|int $organization): int
    {
        $organizationId = $organization instanceof Organization
            ? $organization->id
            : $organization;

        return (int) Issue::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', IssueStatus::activeStatusValues())
            ->selectRaw('SUM(risk_weight * occurrence_count) as score')
            ->value('score');
    }
}
