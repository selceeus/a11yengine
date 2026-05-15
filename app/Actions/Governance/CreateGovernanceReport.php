<?php

namespace App\Actions\Governance;

use App\Enums\GovernanceReportStatus;
use App\Jobs\GenerateGovernanceReportJob;
use App\Models\GovernanceReport;

class CreateGovernanceReport
{
    /**
     * Create a pending GovernanceReport and dispatch the generation job.
     *
     * @param  array{agency_id: int, organization_id: int|null, property_id: int|null, report_scope: string, period_from: string, period_to: string}  $data
     */
    public function handle(array $data): GovernanceReport
    {
        $report = GovernanceReport::withoutGlobalScopes()->create([
            ...$data,
            'status' => GovernanceReportStatus::Pending,
            'is_scheduled' => false,
        ]);

        GenerateGovernanceReportJob::dispatch($report);

        return $report;
    }
}
