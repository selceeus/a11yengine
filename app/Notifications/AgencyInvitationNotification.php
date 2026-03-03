<?php

namespace App\Notifications;

use App\Models\AgencyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgencyInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AgencyInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $agencyName = $this->invitation->agency->name;
        $url = route('invitations.show', $this->invitation->token);

        return (new MailMessage)
            ->subject("You've been invited to join {$agencyName}")
            ->greeting("You've been invited!")
            ->line("You have been invited to join {$agencyName} on Carbon Base Digital.")
            ->action('Accept Invitation', $url)
            ->line('This invitation link will expire in 7 days.')
            ->line('If you did not expect this invitation, you can safely ignore this email.');
    }
}
