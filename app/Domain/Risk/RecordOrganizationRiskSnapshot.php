<?php

namespace App\Domain\Risk;

use App\Models\Organization;
use App\Models\OrganizationRiskSnapshot;
use Illuminate\Support\Facades\Date;

class RecordOrganizationRiskSnapshot
{
    public function __construct(private readonly CalculateOrganizationRiskScore $calculator) {}

    public function handle(Organization|int $organization): OrganizationRiskSnapshot
    {
        $organizationId = $organization instanceof Organization
            ? $organization->id
            : $organization;

        $score = $this->calculator->handle($organizationId);

        return OrganizationRiskSnapshot::query()->create([
            'organization_id' => $organizationId,
            'risk_score' => $score,
            'calculated_at' => Date::now(),
        ]);
    }
}
