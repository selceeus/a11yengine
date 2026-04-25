<?php

namespace App\Observers;

use App\Enums\ActivityLogEvent;
use App\Enums\IssueActivityType;
use App\Enums\IssueSeverity;
use App\Models\Issue;
use App\Models\IssueActivity;
use App\Services\ActivityLogger;
use Carbon\CarbonImmutable;

class IssueObserver
{
    public function creating(Issue $issue): void
    {
        if ($issue->due_date === null && $issue->severity !== null) {
            $issue->due_date = self::defaultDueDateForSeverity($issue->severity);
        }
    }

    public function updated(Issue $issue): void
    {
        if ($issue->wasChanged('status')) {
            IssueActivity::create([
                'issue_id' => $issue->id,
                'user_id' => auth()->id(),
                'type' => IssueActivityType::StatusChange,
                'metadata' => [
                    'from' => $issue->getOriginal('status'),
                    'to' => $issue->status->value,
                ],
                'created_at' => now(),
            ]);

            ActivityLogger::log(
                event: ActivityLogEvent::IssueStatusChanged,
                subject: $issue,
                subjectLabel: $issue->description ? mb_substr($issue->description, 0, 80) : "Issue #{$issue->id}",
                metadata: [
                    'from' => $issue->getOriginal('status'),
                    'to' => $issue->status->value,
                ],
            );
        }

        if ($issue->wasChanged('assigned_user_id')) {
            IssueActivity::create([
                'issue_id' => $issue->id,
                'user_id' => auth()->id(),
                'type' => IssueActivityType::Assignment,
                'metadata' => [
                    'from_user_id' => $issue->getOriginal('assigned_user_id'),
                    'to_user_id' => $issue->assigned_user_id,
                ],
                'created_at' => now(),
            ]);

            ActivityLogger::log(
                event: ActivityLogEvent::IssueAssigned,
                subject: $issue,
                subjectLabel: $issue->description ? mb_substr($issue->description, 0, 80) : "Issue #{$issue->id}",
                metadata: [
                    'to_user_id' => $issue->assigned_user_id,
                ],
            );
        }

        if ($issue->wasChanged('due_date')) {
            IssueActivity::create([
                'issue_id' => $issue->id,
                'user_id' => auth()->id(),
                'type' => IssueActivityType::DueDateChange,
                'metadata' => [
                    'from' => $issue->getOriginal('due_date'),
                    'to' => $issue->due_date?->toDateString(),
                ],
                'created_at' => now(),
            ]);
        }
    }

    public static function defaultDueDateForSeverity(IssueSeverity $severity): CarbonImmutable
    {
        $days = match ($severity) {
            IssueSeverity::Critical => 7,
            IssueSeverity::High => 14,
            IssueSeverity::Medium => 30,
            IssueSeverity::Low => 60,
        };

        return now()->addDays($days);
    }
}
