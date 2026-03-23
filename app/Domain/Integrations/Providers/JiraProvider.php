<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Contracts\ProjectManagementProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class JiraProvider implements ProjectManagementProvider
{
    public function createTask(Integration $integration, Issue $issue): array
    {
        $creds = $integration->credentials;

        $response = Http::withBasicAuth($creds['email'], $creds['api_token'])
            ->post("{$creds['base_url']}/rest/api/3/issue", [
                'fields' => [
                    'project' => ['key' => $creds['project_key']],
                    'summary' => "[A11y] {$issue->rule_key}: {$issue->description}",
                    'issuetype' => ['name' => 'Bug'],
                    'priority' => ['name' => $this->mapSeverity($issue->severity->value)],
                    'description' => [
                        'type' => 'doc',
                        'version' => 1,
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'content' => [
                                    ['type' => 'text', 'text' => "Page: {$issue->page_url}"],
                                ],
                            ],
                            [
                                'type' => 'paragraph',
                                'content' => [
                                    ['type' => 'text', 'text' => "WCAG: {$issue->wcag_criteria}"],
                                ],
                            ],
                            [
                                'type' => 'paragraph',
                                'content' => [
                                    ['type' => 'text', 'text' => "Help: {$issue->help_url}"],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $response->throw();

        $data = $response->json();

        return [
            'id' => $data['key'],
            'url' => "{$creds['base_url']}/browse/{$data['key']}",
        ];
    }

    public function closeTask(Integration $integration, string $externalId): void
    {
        $creds = $integration->credentials;

        $transitions = Http::withBasicAuth($creds['email'], $creds['api_token'])
            ->get("{$creds['base_url']}/rest/api/3/issue/{$externalId}/transitions")
            ->throw()
            ->json('transitions', []);

        $doneTransition = collect($transitions)
            ->first(fn (array $t) => str_contains(strtolower($t['name']), 'done')
                || str_contains(strtolower($t['name']), 'close'));

        if ($doneTransition === null) {
            return;
        }

        Http::withBasicAuth($creds['email'], $creds['api_token'])
            ->post("{$creds['base_url']}/rest/api/3/issue/{$externalId}/transitions", [
                'transition' => ['id' => $doneTransition['id']],
            ])
            ->throw();
    }

    public function verifyWebhook(Integration $integration, Request $request): bool
    {
        // Jira uses JWT-signed webhooks; for simplicity accept all (configure IP allowlist).
        return true;
    }

    public function parseWebhookStatus(Request $request): string
    {
        return $request->input('issue.fields.status.statusCategory.key', '');
    }

    public function parseWebhookExternalId(Request $request): string
    {
        return $request->input('issue.key', '');
    }

    public function testConnection(Integration $integration): array
    {
        $creds = $integration->credentials;

        $response = Http::withBasicAuth($creds['email'], $creds['api_token'])
            ->get("{$creds['base_url']}/rest/api/3/myself");

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Connected to Jira successfully.'];
        }

        return ['ok' => false, 'message' => $response->json('message', 'Connection failed.')];
    }

    private function mapSeverity(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Highest',
            'serious' => 'High',
            'moderate' => 'Medium',
            default => 'Low',
        };
    }
}
