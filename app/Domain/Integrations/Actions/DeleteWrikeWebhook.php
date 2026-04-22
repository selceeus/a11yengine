<?php

namespace App\Domain\Integrations\Actions;

use App\Models\Integration;
use Illuminate\Support\Facades\Http;

class DeleteWrikeWebhook
{
    private const BASE_URL = 'https://www.wrike.com/api/v4';

    public function __invoke(Integration $integration): void
    {
        $webhookId = $integration->settings['wrike_webhook_id'] ?? null;

        if (empty($webhookId)) {
            return;
        }

        $creds = $integration->credentials;

        try {
            Http::withToken($creds['access_token'])
                ->delete(self::BASE_URL."/webhooks/{$webhookId}");
        } finally {
            $settings = $integration->settings ?? [];
            unset($settings['wrike_webhook_id'], $settings['webhook_secret']);

            $integration->update(['settings' => $settings]);
        }
    }
}
