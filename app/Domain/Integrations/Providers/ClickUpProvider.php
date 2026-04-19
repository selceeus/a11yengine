<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Contracts\ProjectManagementProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ClickUpProvider implements ProjectManagementProvider
{
    private const BASE_URL = 'https://api.clickup.com/api/v2';

    public function createTask(Integration $integration, Issue $issue): array
    {
        $creds = $integration->credentials;

        $response = Http::withHeaders(['Authorization' => $creds['api_token']])
            ->post(self::BASE_URL."/list/{$creds['list_id']}/task", [
                'name' => "[A11y] {$issue->rule_key}: {$issue->description}",
                'description' => implode("\n", [
                    "Page: {$issue->page_url}",
                    "WCAG: {$issue->wcag_criteria}",
                    "Severity: {$issue->severity->value}",
                    "Help: {$issue->help_url}",
                ]),
                'priority' => $this->mapSeverity($issue->severity->value),
            ]);

        $response->throw();

        $task = $response->json();

        return [
            'id' => $task['id'],
            'url' => $task['url'] ?? null,
        ];
    }

    public function closeTask(Integration $integration, string $externalId): void
    {
        $creds = $integration->credentials;

        Http::withHeaders(['Authorization' => $creds['api_token']])
            ->put(self::BASE_URL."/task/{$externalId}", [
                'status' => 'complete',
            ])
            ->throw();
    }

    public function verifyWebhook(Integration $integration, Request $request): bool
    {
        return true;
    }

    public function parseWebhookStatus(Request $request): string
    {
        return $request->input('task_status.status', '');
    }

    public function parseWebhookExternalId(Request $request): string
    {
        return $request->input('task_id', '');
    }

    public function testConnection(Integration $integration): array
    {
        $creds = $integration->credentials;

        $response = Http::withHeaders(['Authorization' => $creds['api_token']])
            ->get(self::BASE_URL.'/user');

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Connected to ClickUp successfully.'];
        }

        return ['ok' => false, 'message' => $response->json('err', 'Connection failed.')];
    }

    private function mapSeverity(string $severity): int
    {
        return match ($severity) {
            'critical' => 1,
            'serious' => 2,
            'moderate' => 3,
            default => 4,
        };
    }
}
