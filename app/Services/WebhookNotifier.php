<?php

namespace App\Services;

use App\Jobs\SendWebhookNotificationJob;
use App\Models\Agency;
use App\Models\NotificationWebhookRoute;

class WebhookNotifier
{
    public function notify(Agency|int $agency, string $category, string $title, string $body): void
    {
        $webhooks = NotificationWebhookRoute::getWebhooksForCategory($agency, $category);

        foreach ($webhooks as $webhook) {
            SendWebhookNotificationJob::dispatch(
                $webhook['url'],
                $webhook['platform'],
                $title,
                $body,
            );
        }
    }
}
