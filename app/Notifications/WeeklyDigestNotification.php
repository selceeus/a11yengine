<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{
     *     agency_name: string,
     *     new_issues: int,
     *     resolved_issues: int,
     *     scans_completed: int,
     *     assigned_open: int,
     *     period_from: string,
     *     period_to: string,
     * }  $digest
     */
    public function __construct(public readonly array $digest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->digest;

        $mail = (new MailMessage)
            ->subject("Weekly Digest — {$d['agency_name']}")
            ->greeting("Weekly Summary for {$d['agency_name']}")
            ->line("Here's your weekly accessibility summary for **{$d['period_from']}** to **{$d['period_to']}**:")
            ->line("- **{$d['scans_completed']}** scans completed")
            ->line("- **{$d['new_issues']}** new issues detected")
            ->line("- **{$d['resolved_issues']}** issues resolved");

        if ($d['assigned_open'] > 0) {
            $mail->line("- **{$d['assigned_open']}** issues assigned to you are still open");
        }

        $mail->action('Go to Dashboard', route('dashboard'))
            ->line('Keep up the great work improving accessibility!');

        return $mail;
    }
}
