<?php

namespace App\Console\Commands;

use App\Domain\Risk\RecordPropertyRiskSnapshot;
use App\Models\Property;
use Illuminate\Console\Command;

class SnapshotPropertyRisk extends Command
{
    protected $signature = 'snapshots:property-risk';

    protected $description = 'Record a risk snapshot for every property based on its current issue scores';

    public function handle(RecordPropertyRiskSnapshot $recorder): int
    {
        $properties = Property::query()->orderBy('id')->get();

        if ($properties->isEmpty()) {
            $this->info('No properties found — nothing to snapshot.');

            return self::SUCCESS;
        }

        $this->info('Recording property risk snapshots...');
        $bar = $this->output->createProgressBar($properties->count());
        $bar->start();

        $recorded = 0;

        foreach ($properties as $property) {
            $recorder->handle($property);
            $recorded++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Recorded {$recorded} snapshot(s).");

        return self::SUCCESS;
    }
}
