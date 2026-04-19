<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Contracts\ProjectManagementProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MondayProvider implements ProjectManagementProvider
{
    private const BASE_URL = 'https://api.monday.com/v2';

    public function createTask(Integration $integration, Issue $issue): array
    {
        $creds = $integration->credentials;

        $name = "[A11y] {$issue->rule_key}: {$issue->description}";
        $notes = implode("\n", [
            "Page: {$issue->page_url}",
            "WCAG: {$issue->wcag_criteria}",
            "Severity: {$issue->severity->value}",
            "Help: {$issue->help_url}",
        ]);

        $response = Http::withToken($creds['api_token'])
            ->withHeaders(['API-Version' => '2023-10'])
            ->post(self::BASE_URL, [
                'query' => 'mutation ($boardId: ID!, $itemName: String!) { create_item(board_id: $boardId, item_name: $itemName) { id } }',
                'variables' => [
                    'boardId' => $creds['board_id'],
                    'itemName' => $name,
                ],
            ]);

        $response->throw();

        $itemId = $response->json('data.create_item.id');

        // Update the text column with notes
        Http::withToken($creds['api_token'])
            ->withHeaders(['API-Version' => '2023-10'])
            ->post(self::BASE_URL, [
                'query' => 'mutation ($boardId: ID!, $itemId: ID!, $notes: String!) { change_simple_column_value(board_id: $boardId, item_id: $itemId, column_id: "text", value: $notes) { id } }',
                'variables' => [
                    'boardId' => $creds['board_id'],
                    'itemId' => $itemId,
                    'notes' => $notes,
                ],
            ]);

        return [
            'id' => (string) $itemId,
            'url' => "https://monday.com/boards/{$creds['board_id']}/pulses/{$itemId}",
        ];
    }

    public function closeTask(Integration $integration, string $externalId): void
    {
        $creds = $integration->credentials;

        Http::withToken($creds['api_token'])
            ->withHeaders(['API-Version' => '2023-10'])
            ->post(self::BASE_URL, [
                'query' => 'mutation ($boardId: ID!, $itemId: ID!) { change_simple_column_value(board_id: $boardId, item_id: $itemId, column_id: "status", value: "Done") { id } }',
                'variables' => [
                    'boardId' => $creds['board_id'],
                    'itemId' => $externalId,
                ],
            ])
            ->throw();
    }

    public function verifyWebhook(Integration $integration, Request $request): bool
    {
        return true;
    }

    public function parseWebhookStatus(Request $request): string
    {
        return $request->input('event.columnValues.status.label', '');
    }

    public function parseWebhookExternalId(Request $request): string
    {
        return (string) $request->input('event.pulseId', '');
    }

    public function testConnection(Integration $integration): array
    {
        $creds = $integration->credentials;

        $response = Http::withToken($creds['api_token'])
            ->withHeaders(['API-Version' => '2023-10'])
            ->post(self::BASE_URL, [
                'query' => '{ me { name } }',
            ]);

        if ($response->successful() && $response->json('data.me.name')) {
            return ['ok' => true, 'message' => 'Connected to Monday.com successfully.'];
        }

        $error = $response->json('errors.0.message', 'Connection failed.');

        return ['ok' => false, 'message' => $error];
    }
}
