<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\Scan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScanCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Scan $scan) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return collect(['database', 'mail'])
            ->filter(fn (string $channel) => NotificationPreference::isEnabled($notifiable, 'scan_completed', $channel))
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
            'total_violations' => $scan->total_violations ?? 0,
            'pages_scanned' => $scan->pages_scanned ?? 0,
            'completed_at' => $scan->completed_at?->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $scan = $this->scan;
        $propertyName = $scan->property->name;
        $violations = $scan->total_violations ?? 0;

        return (new MailMessage)
            ->subject("Scan completed for {$propertyName}")
            ->greeting('Scan Complete')
            ->line("A scan has completed for **{$propertyName}**.")
            ->line("Total violations found: **{$violations}**")
            ->line("Pages scanned: **{$scan->pages_scanned}**")
            ->action('View Scan Results', route('scans.show', $scan))
            ->line('Review the findings and prioritize remediation efforts.');
    }
}
