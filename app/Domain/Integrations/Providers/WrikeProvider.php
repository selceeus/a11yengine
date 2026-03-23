<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Contracts\ProjectManagementProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WrikeProvider implements ProjectManagementProvider
{
    private const BASE_URL = 'https://www.wrike.com/api/v4';

    public function createTask(Integration $integration, Issue $issue): array
    {
        $creds = $integration->credentials;

        $response = Http::withToken($creds['access_token'])
            ->post(self::BASE_URL."/folders/{$creds['folder_id']}/tasks", [
                'title' => "[A11y] {$issue->rule_key}: {$issue->description}",
                'description' => implode("\n", [
                    "Page: {$issue->page_url}",
                    "WCAG: {$issue->wcag_criteria}",
                    "Severity: {$issue->severity->value}",
                    "Help: {$issue->help_url}",
                ]),
                'importance' => $this->mapSeverity($issue->severity->value),
                'status' => 'Active',
            ]);

        $response->throw();

        $task = $response->json('data.0');

        return [
            'id' => $task['id'],
            'url' => $task['permalink'] ?? null,
        ];
    }

    public function closeTask(Integration $integration, string $externalId): void
    {
        $creds = $integration->credentials;

        Http::withToken($creds['access_token'])
            ->put(self::BASE_URL."/tasks/{$externalId}", ['status' => 'Completed'])
            ->throw();
    }

    public function verifyWebhook(Integration $integration, Request $request): bool
    {
        return true;
    }

    public function parseWebhookStatus(Request $request): string
    {
        return $request->input('data.0.status', '');
    }

    public function parseWebhookExternalId(Request $request): string
    {
        return $request->input('data.0.id', '');
    }

    public function testConnection(Integration $integration): array
    {
        $creds = $integration->credentials;

        $response = Http::withToken($creds['access_token'])
            ->get(self::BASE_URL.'/account');

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Connected to Wrike successfully.'];
        }

        return ['ok' => false, 'message' => $response->json('error_description', 'Connection failed.')];
    }

    private function mapSeverity(string $severity): string
    {
        return match ($severity) {
            'critical', 'serious' => 'High',
            'moderate' => 'Normal',
            default => 'Low',
        };
    }
}
