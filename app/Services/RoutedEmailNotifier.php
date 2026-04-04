<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\NotificationEmailRoute;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class RoutedEmailNotifier
{
    public function notify(Agency|int $agency, string $category, Notification $notification): void
    {
        $emails = NotificationEmailRoute::getEmailsForCategory($agency, $category);

        foreach ($emails as $email) {
            NotificationFacade::route('mail', $email)->notify(clone $notification);
        }
    }
}
