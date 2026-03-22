<?php

namespace App\Notifications;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IssueAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Issue $issue,
        public readonly User $assigner,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $issue = $this->issue;

        return [
            'issue_id' => $issue->id,
            'rule_key' => $issue->rule_key,
            'severity' => $issue->severity->value,
            'property_name' => $issue->property->name,
            'assigner_name' => $this->assigner->name,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $issue = $this->issue;
        $propertyName = $issue->property->name;

        return (new MailMessage)
            ->subject("Issue assigned: {$issue->rule_key} on {$propertyName}")
            ->greeting('Issue Assigned')
            ->line("**{$this->assigner->name}** has assigned you an issue on **{$propertyName}**.")
            ->line("Rule: **{$issue->rule_key}**")
            ->line("Severity: **{$issue->severity->value}**")
            ->action('View Issue', route('issues.show', $issue))
            ->line('Please review and address this issue at your earliest convenience.');
    }
}
