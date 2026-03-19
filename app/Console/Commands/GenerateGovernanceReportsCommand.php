<?php

namespace App\Console\Commands;

use App\Enums\GovernanceReportStatus;
use App\Jobs\GenerateGovernanceReportJob;
use App\Models\GovernanceReport;
use App\Models\Property;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateGovernanceReportsCommand extends Command
{
    protected $signature = 'governance:generate-reports
                            {--agency= : Limit to a specific agency ID}
                            {--days=7 : Number of days for the rolling report period}';

    protected $description = 'Generate scheduled weekly governance reports for all active properties';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $agencyId = $this->option('agency') ? (int) $this->option('agency') : null;

        $periodTo = Carbon::today();
        $periodFrom = $periodTo->copy()->subDays($days);

        $query = Property::withoutGlobalScopes()->where('is_active', true);

        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        $properties = $query->get(['id', 'agency_id', 'organization_id', 'name']);

        if ($properties->isEmpty()) {
            $this->info('No active properties found.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($properties as $property) {
            $report = GovernanceReport::withoutGlobalScopes()->create([
                'agency_id' => $property->agency_id,
                'organization_id' => $property->organization_id,
                'property_id' => $property->id,
                'report_scope' => 'property',
                'period_from' => $periodFrom->toDateString(),
                'period_to' => $periodTo->toDateString(),
                'status' => GovernanceReportStatus::Pending,
                'is_scheduled' => true,
            ]);

            GenerateGovernanceReportJob::dispatch($report);

            $this->line("  dispatched report #{$report->id} for property {$property->name}");
            $dispatched++;
        }

        $this->info("Done. Dispatched {$dispatched} governance report(s).");

        return self::SUCCESS;
    }
}
