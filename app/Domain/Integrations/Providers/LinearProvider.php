<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Contracts\ProjectManagementProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LinearProvider implements ProjectManagementProvider
{
    private const BASE_URL = 'https://api.linear.app/graphql';

    public function createTask(Integration $integration, Issue $issue): array
    {
        $creds = $integration->credentials;

        $description = implode("\n\n", [
            "**Page:** {$issue->page_url}",
            "**WCAG:** {$issue->wcag_criteria}",
            "**Severity:** {$issue->severity->value}",
            "**Help:** {$issue->help_url}",
        ]);

        $mutation = <<<'GQL'
        mutation CreateIssue($title: String!, $description: String!, $teamId: String!, $priority: Int!) {
            issueCreate(input: {
                title: $title,
                description: $description,
                teamId: $teamId,
                priority: $priority
            }) {
                issue {
                    id
                    url
                }
            }
        }
        GQL;

        $response = Http::withHeaders(['Authorization' => $creds['api_key']])
            ->post(self::BASE_URL, [
                'query' => $mutation,
                'variables' => [
                    'title' => "[A11y] {$issue->rule_key}: {$issue->description}",
                    'description' => $description,
                    'teamId' => $creds['team_id'],
                    'priority' => $this->mapSeverity($issue->severity->value),
                ],
            ]);

        $response->throw();

        $node = $response->json('data.issueCreate.issue');

        return [
            'id' => $node['id'],
            'url' => $node['url'] ?? null,
        ];
    }

    public function closeTask(Integration $integration, string $externalId): void
    {
        $creds = $integration->credentials;

        // First resolve the "completed" state ID for the team.
        $statesQuery = <<<'GQL'
        query States($teamId: String!) {
            team(id: $teamId) {
                states { nodes { id name type } }
            }
        }
        GQL;

        $statesResponse = Http::withHeaders(['Authorization' => $creds['api_key']])
            ->post(self::BASE_URL, [
                'query' => $statesQuery,
                'variables' => ['teamId' => $creds['team_id']],
            ])
            ->throw()
            ->json('data.team.states.nodes', []);

        $completedState = collect($statesResponse)
            ->first(fn (array $s) => strtolower($s['type']) === 'completed');

        if ($completedState === null) {
            return;
        }

        $mutation = <<<'GQL'
        mutation UpdateIssue($id: String!, $stateId: String!) {
            issueUpdate(id: $id, input: { stateId: $stateId }) {
                issue { id }
            }
        }
        GQL;

        Http::withHeaders(['Authorization' => $creds['api_key']])
            ->post(self::BASE_URL, [
                'query' => $mutation,
                'variables' => ['id' => $externalId, 'stateId' => $completedState['id']],
            ])
            ->throw();
    }

    public function verifyWebhook(Integration $integration, Request $request): bool
    {
        return true;
    }

    public function parseWebhookStatus(Request $request): string
    {
        return $request->input('data.state.type', '');
    }

    public function parseWebhookExternalId(Request $request): string
    {
        return $request->input('data.id', '');
    }

    public function testConnection(Integration $integration): array
    {
        $creds = $integration->credentials;

        $response = Http::withHeaders(['Authorization' => $creds['api_key']])
            ->post(self::BASE_URL, [
                'query' => '{ viewer { id name } }',
            ]);

        if ($response->successful() && $response->json('data.viewer') !== null) {
            return ['ok' => true, 'message' => 'Connected to Linear successfully.'];
        }

        return ['ok' => false, 'message' => $response->json('errors.0.message', 'Connection failed.')];
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
