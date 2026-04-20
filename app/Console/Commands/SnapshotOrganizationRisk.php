<?php

namespace App\Console\Commands;

use App\Domain\Risk\RecordOrganizationRiskSnapshot;
use App\Models\Organization;
use Illuminate\Console\Command;

class SnapshotOrganizationRisk extends Command
{
    protected $signature = 'snapshots:organization-risk';

    protected $description = 'Record a risk snapshot for every organization based on aggregated property scores';

    public function handle(RecordOrganizationRiskSnapshot $recorder): int
    {
        $organizations = Organization::query()->orderBy('id')->get();

        if ($organizations->isEmpty()) {
            $this->info('No organizations found — nothing to snapshot.');

            return self::SUCCESS;
        }

        $this->info('Recording organization risk snapshots...');
        $bar = $this->output->createProgressBar($organizations->count());
        $bar->start();

        $recorded = 0;

        foreach ($organizations as $organization) {
            $recorder->handle($organization);
            $recorded++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Recorded {$recorded} snapshot(s).");

        return self::SUCCESS;
    }
}
