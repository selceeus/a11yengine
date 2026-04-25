<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SuspiciousLoginNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $email,
        public readonly int $failureCount,
        public readonly ?string $ipAddress,
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
        return [
            'email' => $this->email,
            'failure_count' => $this->failureCount,
            'ip_address' => $this->ipAddress,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Suspicious login activity detected')
            ->error()
            ->greeting('Security Alert')
            ->line("There have been **{$this->failureCount} failed login attempts** for the account **{$this->email}**.")
            ->when(
                $this->ipAddress,
                fn (MailMessage $mail) => $mail->line("Originating IP: {$this->ipAddress}"),
            )
            ->line('If this was not you or a member of your team, we recommend reviewing your team\'s account access immediately.')
            ->action('Review Access', route('access-reviews.index'));
    }
}
