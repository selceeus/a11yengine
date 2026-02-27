<?php

namespace App\Domain\Risk;

use App\Models\Organization;
use App\Models\RiskSnapshot;
use Illuminate\Support\Facades\Date;

class RecordRiskSnapshot
{
    public function __construct(private readonly GetOrganizationRiskSummary $summary) {}

    public function handle(Organization|int $organization): RiskSnapshot
    {
        $organizationId = $organization instanceof Organization
            ? $organization->id
            : $organization;

        $agencyId = $organization instanceof Organization
            ? $organization->agency_id
            : Organization::withoutGlobalScopes()->findOrFail($organizationId)->agency_id;

        $summary = $this->summary->handle($organizationId);

        return RiskSnapshot::query()->create([
            'agency_id' => $agencyId,
            'organization_id' => $organizationId,
            'total_risk_score' => $summary['total_risk_score'],
            'open_issue_count' => $summary['open_issues'],
            'snapshot_date' => Date::today(),
            'created_at' => Date::now(),
        ]);
    }
}
