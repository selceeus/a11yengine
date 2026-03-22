<?php

namespace App\Listeners;

use App\Events\ScanCompleted;
use App\Models\User;
use App\Notifications\ScanCompletedNotification;

class NotifyScanCompleted
{
    public function handle(ScanCompleted $event): void
    {
        $scan = $event->scan;

        $recipients = User::where('agency_id', $scan->agency_id)->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new ScanCompletedNotification($scan));
        }
    }
}
