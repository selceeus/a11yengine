<?php

namespace App\Console\Commands;

use App\Enums\AccessReviewStatus;
use App\Enums\ActivityLogEvent;
use App\Enums\UserRole;
use App\Models\AccessReview;
use App\Models\Agency;
use App\Models\User;
use App\Notifications\AccessReviewDueNotification;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CreateAccessReviewsCommand extends Command
{
    protected $signature = 'access-reviews:create';

    protected $description = 'Create quarterly SOC2 access reviews for all agencies and notify admins';

    public function handle(): int
    {
        $now = Carbon::now();
        $quarter = 'Q'.$now->quarter;
        $period = $now->year.'-'.$quarter;
        $dueAt = $now->copy()->endOfQuarter();

        $agencies = Agency::cursor();

        foreach ($agencies as $agency) {
            $exists = AccessReview::withoutGlobalScopes()
                ->where('agency_id', $agency->id)
                ->where('period', $period)
                ->exists();

            if ($exists) {
                $this->line("  skipped agency #{$agency->id} — review for {$period} already exists");

                continue;
            }

            $review = AccessReview::withoutGlobalScopes()->create([
                'agency_id' => $agency->id,
                'period' => $period,
                'status' => AccessReviewStatus::Pending,
                'due_at' => $dueAt,
            ]);

            ActivityLogger::system(
                agencyId: $agency->id,
                event: ActivityLogEvent::AccessReviewStarted,
                subject: $review,
                subjectLabel: $period,
                metadata: ['period' => $period, 'due_at' => $dueAt->toIso8601String()],
            );

            $admins = User::query()
                ->where('agency_id', $agency->id)
                ->whereHas('roles', fn ($q) => $q->where('role', UserRole::AgencyAdmin->value))
                ->get();

            foreach ($admins as $admin) {
                $admin->notify(new AccessReviewDueNotification($review));
            }

            $this->line("  created review #{$review->id} for agency #{$agency->id} ({$period})");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
