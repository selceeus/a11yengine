<?php

namespace App\Console\Commands;

use App\Enums\ActivityLogEvent;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneActivityLogCommand extends Command
{
    protected $signature = 'activity-log:prune
                            {--months= : Override the retention window in months}';

    protected $description = 'Delete activity log entries older than the configured retention window';

    public function handle(): int
    {
        $months = (int) ($this->option('months') ?? config('app.activity_log_retention_months'));
        $cutoff = Carbon::now()->subMonths($months)->startOfDay();

        $this->line("Pruning activity log entries older than {$cutoff->toDateString()} ({$months} month retention)...");

        // Collect distinct agency IDs before deleting so we can write one log entry per agency
        $agencyIds = ActivityLog::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->distinct()
            ->pluck('agency_id')
            ->filter()
            ->values();

        if ($agencyIds->isEmpty()) {
            $this->info('Nothing to prune.');

            return self::SUCCESS;
        }

        // Delete and log per agency to track accurate per-agency counts
        $totalDeleted = 0;

        foreach ($agencyIds as $agencyId) {
            $agencyTotal = 0;

            do {
                $deleted = ActivityLog::withoutGlobalScopes()
                    ->where('agency_id', $agencyId)
                    ->where('created_at', '<', $cutoff)
                    ->limit(500)
                    ->delete();

                $agencyTotal += $deleted;
            } while ($deleted > 0);

            $totalDeleted += $agencyTotal;

            ActivityLogger::system(
                agencyId: $agencyId,
                event: ActivityLogEvent::ActivityLogPruned,
                metadata: [
                    'deleted_count' => $agencyTotal,
                    'cutoff' => $cutoff->toIso8601String(),
                    'retention_months' => $months,
                ],
            );
        }

        $this->info("Done. Pruned {$totalDeleted} log entries across {$agencyIds->count()} agency/agencies.");

        return self::SUCCESS;
    }
}
