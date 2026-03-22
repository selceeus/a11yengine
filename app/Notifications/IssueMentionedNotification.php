<?php

namespace App\Notifications;

use App\Models\Issue;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IssueMentionedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Issue $issue,
        public readonly User $mentionedBy,
        public readonly string $comment,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return collect(['database', 'mail'])
            ->filter(fn (string $channel) => NotificationPreference::isEnabled($notifiable, 'issue_mentioned', $channel))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'issue_id' => $this->issue->id,
            'rule_key' => $this->issue->rule_key,
            'severity' => $this->issue->severity->value,
            'property_name' => $this->issue->property?->name,
            'mentioned_by_name' => $this->mentionedBy->name,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $issue = $this->issue;
        $propertyName = $issue->property?->name ?? 'Unknown property';

        return (new MailMessage)
            ->subject("You were mentioned on: {$issue->rule_key}")
            ->greeting('You were mentioned')
            ->line("**{$this->mentionedBy->name}** mentioned you in a comment on issue **{$issue->rule_key}** on **{$propertyName}**.")
            ->line("Comment: \"{$this->comment}\"")
            ->action('View Issue', route('issues.show', $issue))
            ->line('Click above to view the issue and respond.');
    }
}
