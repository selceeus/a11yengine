<?php

namespace App\Listeners;

use App\Enums\NotificationEmailCategory;
use App\Events\ScanFailed;
use App\Models\User;
use App\Notifications\ScanFailedNotification;
use App\Services\RoutedEmailNotifier;
use App\Services\WebhookNotifier;

class NotifyScanFailed
{
    public function handle(ScanFailed $event): void
    {
        $scan = $event->scan;
        $property = $scan->property;

        $recipients = User::where('agency_id', $scan->agency_id)->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new ScanFailedNotification($scan));
        }

        $title = "Scan failed: {$property->name}";
        $body = $scan->error_message ?? 'An unexpected error occurred during the scan.';

        app(RoutedEmailNotifier::class)->notify(
            $scan->agency_id,
            NotificationEmailCategory::ScanFailures->value,
            new ScanFailedNotification($scan),
        );

        app(WebhookNotifier::class)->notify(
            $scan->agency_id,
            NotificationEmailCategory::ScanFailures->value,
            $title,
            $body,
        );
    }
}
