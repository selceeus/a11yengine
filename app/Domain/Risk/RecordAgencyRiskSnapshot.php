<?php

namespace App\Domain\Risk;

use App\Models\Agency;
use App\Models\AgencyRiskSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

class RecordAgencyRiskSnapshot
{
    public function __construct(private readonly RollUpAgencyRiskSnapshots $rollUp) {}

    public function handle(Agency|int $agency, ?CarbonInterface $asOf = null): AgencyRiskSnapshot
    {
        $asOf ??= Date::today();
        $agencyId = $agency instanceof Agency ? $agency->id : $agency;

        $summary = $this->rollUp->handle($agencyId, $asOf);

        return AgencyRiskSnapshot::query()->create([
            'agency_id' => $agencyId,
            'risk_score' => $summary['total_risk_score'],
            'open_issue_count' => $summary['total_open_issue_count'],
            'snapshot_date' => $asOf->toDateString(),
            'created_at' => Date::now(),
        ]);
    }
}
