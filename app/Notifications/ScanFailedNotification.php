<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\Scan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScanFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Scan $scan) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return collect(['database', 'mail'])
            ->filter(fn (string $channel) => NotificationPreference::isEnabled($notifiable, 'scan_failed', $channel))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $scan = $this->scan;
        $property = $scan->property;

        return [
            'scan_id' => $scan->id,
            'property_id' => $property->id,
            'property_name' => $property->name,
            'error_message' => $scan->error_message,
            'failed_at' => $scan->updated_at?->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $scan = $this->scan;
        $propertyName = $scan->property->name;

        return (new MailMessage)
            ->subject("Scan failed for {$propertyName}")
            ->error()
            ->greeting('Scan Failed')
            ->line("A scan for **{$propertyName}** has failed.")
            ->when(
                $scan->error_message,
                fn (MailMessage $mail) => $mail->line('Error: '.$scan->error_message),
            )
            ->action('View Scan', route('scans.show', $scan))
            ->line('Please retry the scan or contact support if the problem persists.');
    }
}
