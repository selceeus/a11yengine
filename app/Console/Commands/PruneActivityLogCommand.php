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

        // Chunk-delete to avoid long-running table locks
        $total = 0;

        do {
            $deleted = ActivityLog::withoutGlobalScopes()
                ->where('created_at', '<', $cutoff)
                ->limit(500)
                ->delete();

            $total += $deleted;
        } while ($deleted > 0);

        if ($total === 0) {
            $this->info('Nothing to prune.');

            return self::SUCCESS;
        }

        // Log the prune event once per affected agency
        foreach ($agencyIds as $agencyId) {
            ActivityLogger::system(
                agencyId: $agencyId,
                event: ActivityLogEvent::ActivityLogPruned,
                metadata: [
                    'deleted_count' => $total,
                    'cutoff' => $cutoff->toIso8601String(),
                    'retention_months' => $months,
                ],
            );
        }

        $this->info("Done. Pruned {$total} log entries across {$agencyIds->count()} agency/agencies.");

        return self::SUCCESS;
    }
}
