<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Contracts\ProjectManagementProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TrelloProvider implements ProjectManagementProvider
{
    private const BASE_URL = 'https://api.trello.com/1';

    public function createTask(Integration $integration, Issue $issue): array
    {
        $creds = $integration->credentials;

        $response = Http::post(self::BASE_URL.'/cards', [
            'key' => $creds['api_key'],
            'token' => $creds['token'],
            'idList' => $creds['list_id'],
            'name' => "[A11y] {$issue->rule_key}: {$issue->description}",
            'desc' => implode("\n", [
                "Page: {$issue->page_url}",
                "WCAG: {$issue->wcag_criteria}",
                "Severity: {$issue->severity->value}",
                "Help: {$issue->help_url}",
            ]),
        ]);

        $response->throw();

        $card = $response->json();

        return [
            'id' => $card['id'],
            'url' => $card['shortUrl'] ?? $card['url'] ?? null,
        ];
    }

    public function closeTask(Integration $integration, string $externalId): void
    {
        $creds = $integration->credentials;

        Http::put(self::BASE_URL."/cards/{$externalId}", [
            'key' => $creds['api_key'],
            'token' => $creds['token'],
            'closed' => 'true',
        ])->throw();
    }

    public function verifyWebhook(Integration $integration, Request $request): bool
    {
        return true;
    }

    public function parseWebhookStatus(Request $request): string
    {
        return $request->input('action.type', '');
    }

    public function parseWebhookExternalId(Request $request): string
    {
        return $request->input('action.data.card.id', '');
    }

    public function testConnection(Integration $integration): array
    {
        $creds = $integration->credentials;

        $response = Http::get(self::BASE_URL.'/members/me', [
            'key' => $creds['api_key'],
            'token' => $creds['token'],
        ]);

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Connected to Trello successfully.'];
        }

        return ['ok' => false, 'message' => $response->json('message', 'Connection failed.')];
    }
}
