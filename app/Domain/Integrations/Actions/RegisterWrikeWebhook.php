<?php

namespace App\Domain\Integrations\Actions;

use App\Models\Integration;
use Illuminate\Support\Facades\Http;

class RegisterWrikeWebhook
{
    private const BASE_URL = 'https://www.wrike.com/api/v4';

    public function __invoke(Integration $integration): void
    {
        if (! empty($integration->settings['wrike_webhook_id'])) {
            return;
        }

        $creds = $integration->credentials;
        $callbackUrl = route('api.webhooks.integrations', $integration);

        $response = Http::withToken($creds['access_token'])
            ->asForm()
            ->post(self::BASE_URL.'/webhooks', [
                'hookUrl' => $callbackUrl,
            ]);

        $response->throw();

        $webhook = $response->json('data.0');

        $integration->update([
            'settings' => array_merge($integration->settings ?? [], [
                'wrike_webhook_id' => $webhook['id'],
                'webhook_secret' => $webhook['secretKey'],
            ]),
        ]);
    }
}
