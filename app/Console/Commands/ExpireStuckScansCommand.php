<?php

namespace App\Console\Commands;

use App\Domain\Scans\Scan as ScanDomain;
use App\Enums\ScanStatus;
use App\Models\Scan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ExpireStuckScansCommand extends Command
{
    protected $signature = 'scans:expire-stuck';

    protected $description = 'Fail any scans that have been in the running state for more than 20 minutes';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subMinutes(20);

        $stuck = Scan::withoutGlobalScopes()
            ->where('status', ScanStatus::Running)
            ->where('started_at', '<', $cutoff)
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck scans found.');

            return self::SUCCESS;
        }

        $scanDomain = new ScanDomain;

        foreach ($stuck as $scan) {
            $scanDomain->fail($scan, 'Scan timed out after 20 minutes.');
            $this->line("  expired scan #{$scan->id} (started {$scan->started_at})");
        }

        $this->info("Expired {$stuck->count()} stuck scan(s).");

        return self::SUCCESS;
    }
}
