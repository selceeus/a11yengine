<?php

namespace App\Console\Commands;

use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\NotificationPreference;
use App\Models\Scan;
use App\Notifications\WeeklyDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendWeeklyDigestCommand extends Command
{
    protected $signature = 'digest:weekly
                            {--agency= : Limit to a specific agency ID}';

    protected $description = 'Send weekly accessibility digest emails to all users';

    public function handle(): int
    {
        $periodTo = Carbon::today();
        $periodFrom = $periodTo->copy()->subWeek();

        $query = Agency::query();

        if ($agencyId = $this->option('agency')) {
            $query->where('id', (int) $agencyId);
        }

        $agencies = $query->with('users')->get();

        $sent = 0;

        foreach ($agencies as $agency) {
            $newIssues = Issue::withoutGlobalScopes()
                ->where('agency_id', $agency->id)
                ->whereBetween('first_detected_at', [$periodFrom, $periodTo])
                ->count();

            $resolvedIssues = Issue::withoutGlobalScopes()
                ->where('agency_id', $agency->id)
                ->where('status', IssueStatus::Resolved)
                ->whereBetween('resolved_at', [$periodFrom, $periodTo])
                ->count();

            $scansCompleted = Scan::withoutGlobalScopes()
                ->where('agency_id', $agency->id)
                ->whereBetween('completed_at', [$periodFrom, $periodTo])
                ->count();

            foreach ($agency->users as $user) {
                if (! NotificationPreference::isEnabled($user, 'weekly_digest', 'mail')) {
                    continue;
                }

                $assignedOpen = Issue::withoutGlobalScopes()
                    ->where('agency_id', $agency->id)
                    ->where('assigned_user_id', $user->id)
                    ->whereIn('status', [IssueStatus::Open, IssueStatus::InProgress])
                    ->count();

                $user->notify(new WeeklyDigestNotification([
                    'agency_name' => $agency->name,
                    'new_issues' => $newIssues,
                    'resolved_issues' => $resolvedIssues,
                    'scans_completed' => $scansCompleted,
                    'assigned_open' => $assignedOpen,
                    'period_from' => $periodFrom->toDateString(),
                    'period_to' => $periodTo->toDateString(),
                ]));

                $sent++;
            }
        }

        $this->info("Sent weekly digest to {$sent} users.");

        return self::SUCCESS;
    }
}
