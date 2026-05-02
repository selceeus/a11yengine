<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Contracts\ProjectManagementProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AsanaProvider implements ProjectManagementProvider
{
    private const BASE_URL = 'https://app.asana.com/api/1.0';

    public function createTask(Integration $integration, Issue $issue): array
    {
        $creds = $integration->credentials;

        $response = Http::withToken($creds['access_token'])
            ->post(self::BASE_URL.'/tasks', [
                'data' => [
                    'name' => "[A11y] {$issue->rule_key}: {$issue->description}",
                    'notes' => implode("\n", [
                        "Page: {$issue->page_url}",
                        "WCAG: {$issue->wcag_criteria}",
                        "Severity: {$issue->severity->value}",
                        "Help: {$issue->help_url}",
                    ]),
                    'projects' => [$creds['project_gid']],
                ],
            ]);

        $response->throw();

        $task = $response->json('data');

        return [
            'id' => $task['gid'],
            'url' => "https://app.asana.com/0/{$creds['project_gid']}/{$task['gid']}",
        ];
    }

    public function closeTask(Integration $integration, string $externalId): void
    {
        $creds = $integration->credentials;

        Http::withToken($creds['access_token'])
            ->put(self::BASE_URL."/tasks/{$externalId}", [
                'data' => ['completed' => true],
            ])
            ->throw();
    }

    public function verifyWebhook(Integration $integration, Request $request): bool
    {
        $creds = $integration->credentials;
        $secret = $creds['webhook_secret'] ?? null;

        if (empty($secret)) {
            return false;
        }

        $signature = $request->header('X-Hook-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhookStatus(Request $request): string
    {
        $events = $request->input('events', []);

        foreach ($events as $event) {
            if (($event['resource']['resource_type'] ?? '') === 'task') {
                return $event['action'] ?? '';
            }
        }

        return '';
    }

    public function parseWebhookExternalId(Request $request): string
    {
        $events = $request->input('events', []);

        foreach ($events as $event) {
            if (($event['resource']['resource_type'] ?? '') === 'task') {
                return $event['resource']['gid'] ?? '';
            }
        }

        return '';
    }

    public function testConnection(Integration $integration): array
    {
        $creds = $integration->credentials;

        $response = Http::withToken($creds['access_token'])
            ->get(self::BASE_URL.'/users/me');

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Connected to Asana successfully.'];
        }

        return ['ok' => false, 'message' => $response->json('errors.0.message', 'Connection failed.')];
    }
}
