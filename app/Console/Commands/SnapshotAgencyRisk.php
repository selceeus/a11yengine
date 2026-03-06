<?php

namespace App\Console\Commands;

use App\Domain\Risk\RecordAgencyRiskSnapshot;
use App\Models\Agency;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SnapshotAgencyRisk extends Command
{
    protected $signature = 'snapshots:agency-risk
                            {--date= : Snapshot date in Y-m-d format (defaults to today)}';

    protected $description = 'Record a risk snapshot for every agency based on aggregated property scores';

    public function handle(RecordAgencyRiskSnapshot $recorder): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::today();

        $agencies = Agency::query()->orderBy('id')->get();

        if ($agencies->isEmpty()) {
            $this->info('No agencies found — nothing to snapshot.');

            return self::SUCCESS;
        }

        $this->info("Recording agency risk snapshots for {$date->toDateString()}...");
        $bar = $this->output->createProgressBar($agencies->count());
        $bar->start();

        $recorded = 0;

        foreach ($agencies as $agency) {
            $recorder->handle($agency, $date);
            $recorded++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Recorded {$recorded} snapshot(s).");

        return self::SUCCESS;
    }
}
