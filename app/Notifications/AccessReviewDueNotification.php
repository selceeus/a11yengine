<?php

namespace App\Notifications;

use App\Models\AccessReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccessReviewDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AccessReview $review) {}

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
            'review_id' => $this->review->id,
            'period' => $this->review->period,
            'due_at' => $this->review->due_at->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("SOC2 Access Review due — {$this->review->period}")
            ->greeting('Access Review Required')
            ->line("Your quarterly SOC2 access review for **{$this->review->period}** is due by **{$this->review->due_at->format('F j, Y')}**.")
            ->line('Please review all team members\' roles and confirm or revoke access as appropriate.')
            ->action('Start Access Review', route('access-reviews.show', $this->review));
    }
}
