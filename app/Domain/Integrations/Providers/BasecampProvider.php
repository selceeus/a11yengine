<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Contracts\ProjectManagementProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BasecampProvider implements ProjectManagementProvider
{
    private const BASE_URL = 'https://3.basecampapi.com';

    private const USER_AGENT = 'A11yEngine (support@yourapp.com)';

    public function createTask(Integration $integration, Issue $issue): array
    {
        $creds = $integration->credentials;
        $todoUrl = self::BASE_URL."/{$creds['account_id']}/buckets/{$creds['project_id']}/todolists/{$creds['todolist_id']}/todos.json";

        $response = Http::withToken($creds['access_token'])
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->post($todoUrl, [
                'content' => "[A11y] {$issue->rule_key}: {$issue->description}",
                'description' => implode("\n", [
                    "Page: {$issue->page_url}",
                    "WCAG: {$issue->wcag_criteria}",
                    "Severity: {$issue->severity->value}",
                    "Help: {$issue->help_url}",
                ]),
            ]);

        $response->throw();

        $todo = $response->json();

        return [
            'id' => (string) $todo['id'],
            'url' => $todo['app_url'] ?? null,
        ];
    }

    public function closeTask(Integration $integration, string $externalId): void
    {
        $creds = $integration->credentials;

        Http::withToken($creds['access_token'])
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->post(self::BASE_URL."/{$creds['account_id']}/buckets/{$creds['project_id']}/todos/{$externalId}/completion.json")
            ->throw();
    }

    public function verifyWebhook(Integration $integration, Request $request): bool
    {
        $creds = $integration->credentials;
        $secret = $creds['webhook_secret'] ?? null;

        if (empty($secret)) {
            return true;
        }

        $signature = $request->header('X-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhookStatus(Request $request): string
    {
        return $request->input('kind', '');
    }

    public function parseWebhookExternalId(Request $request): string
    {
        return (string) $request->input('recording.id', '');
    }

    public function testConnection(Integration $integration): array
    {
        $creds = $integration->credentials;

        $response = Http::withToken($creds['access_token'])
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get(self::BASE_URL.'/authorization.json');

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Connected to Basecamp successfully.'];
        }

        return ['ok' => false, 'message' => $response->json('error', 'Connection failed.')];
    }
}
