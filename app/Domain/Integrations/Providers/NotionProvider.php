<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Contracts\ProjectManagementProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NotionProvider implements ProjectManagementProvider
{
    private const BASE_URL = 'https://api.notion.com/v1';

    private const NOTION_VERSION = '2022-06-28';

    public function createTask(Integration $integration, Issue $issue): array
    {
        $creds = $integration->credentials;

        $response = Http::withToken($creds['integration_token'])
            ->withHeaders(['Notion-Version' => self::NOTION_VERSION])
            ->post(self::BASE_URL.'/pages', [
                'parent' => ['database_id' => $creds['database_id']],
                'properties' => [
                    'Name' => [
                        'title' => [
                            ['text' => ['content' => "[A11y] {$issue->rule_key}: {$issue->description}"]],
                        ],
                    ],
                ],
                'children' => [
                    [
                        'object' => 'block',
                        'type' => 'paragraph',
                        'paragraph' => [
                            'rich_text' => [
                                ['type' => 'text', 'text' => ['content' => implode("\n", [
                                    "Page: {$issue->page_url}",
                                    "WCAG: {$issue->wcag_criteria}",
                                    "Severity: {$issue->severity->value}",
                                    "Help: {$issue->help_url}",
                                ])]],
                            ],
                        ],
                    ],
                ],
            ]);

        $response->throw();

        $page = $response->json();

        return [
            'id' => $page['id'],
            'url' => $page['url'] ?? null,
        ];
    }

    public function closeTask(Integration $integration, string $externalId): void
    {
        $creds = $integration->credentials;

        Http::withToken($creds['integration_token'])
            ->withHeaders(['Notion-Version' => self::NOTION_VERSION])
            ->patch(self::BASE_URL."/pages/{$externalId}", [
                'archived' => true,
            ])
            ->throw();
    }

    public function verifyWebhook(Integration $integration, Request $request): bool
    {
        $creds = $integration->credentials;
        $secret = $creds['webhook_secret'] ?? null;

        if (empty($secret)) {
            return true;
        }

        $signature = $request->header('X-Notion-Signature', '');

        if (empty($signature)) {
            return false;
        }

        // Notion sends "sha256=<hex_digest>" in the X-Notion-Signature header.
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhookStatus(Request $request): string
    {
        return $request->input('data.object.properties.Status.status.name', '');
    }

    public function parseWebhookExternalId(Request $request): string
    {
        return $request->input('data.object.id', '');
    }

    public function testConnection(Integration $integration): array
    {
        $creds = $integration->credentials;

        $response = Http::withToken($creds['integration_token'])
            ->withHeaders(['Notion-Version' => self::NOTION_VERSION])
            ->get(self::BASE_URL.'/databases/'.$creds['database_id']);

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Connected to Notion successfully.'];
        }

        return ['ok' => false, 'message' => $response->json('message', 'Connection failed.')];
    }
}
