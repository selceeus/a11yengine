<?php

namespace App\Console\Commands;

use App\Enums\ScanStatus;
use App\Jobs\RunScanJob;
use App\Models\ScheduledScan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RunScheduledScansCommand extends Command
{
    protected $signature = 'scans:run-scheduled';

    protected $description = 'Fire any scheduled scans whose next_run_at has passed';

    public function handle(): int
    {
        $now = Carbon::now();

        $due = ScheduledScan::query()
            ->with('property:id,agency_id,organization_id')
            ->where('is_active', true)
            ->where('next_run_at', '<=', $now)
            ->get();

        if ($due->isEmpty()) {
            $this->info('No scheduled scans due.');

            return self::SUCCESS;
        }

        foreach ($due as $schedule) {
            $scan = \App\Models\Scan::create([
                'agency_id' => $schedule->agency_id,
                'organization_id' => $schedule->organization_id,
                'property_id' => $schedule->property_id,
                'status' => ScanStatus::Pending,
            ]);

            RunScanJob::dispatch($scan);

            if ($schedule->type === 'once') {
                $schedule->update([
                    'is_active' => false,
                    'last_run_at' => $now,
                ]);
            } else {
                $schedule->update([
                    'last_run_at' => $now,
                    'next_run_at' => $schedule->computeNextRunAt($now),
                ]);
            }

            $this->line("  dispatched scan #{$scan->id} for property {$schedule->property_id}");
        }

        $this->info("Done. Dispatched {$due->count()} scan(s).");

        return self::SUCCESS;
    }
}
