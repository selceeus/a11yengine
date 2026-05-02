<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Contracts\ProjectManagementProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GitHubProvider implements ProjectManagementProvider
{
    private const BASE_URL = 'https://api.github.com';

    public function createTask(Integration $integration, Issue $issue): array
    {
        $creds = $integration->credentials;

        $body = implode("\n\n", [
            "**Page:** {$issue->page_url}",
            "**WCAG:** {$issue->wcag_criteria}",
            "**Severity:** {$issue->severity->value}",
            "**Help:** {$issue->help_url}",
        ]);

        $response = Http::withToken($creds['token'])
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->post(self::BASE_URL."/repos/{$creds['owner']}/{$creds['repo']}/issues", [
                'title' => "[A11y] {$issue->rule_key}: {$issue->description}",
                'body' => $body,
                'labels' => ['accessibility', $issue->severity->value],
            ]);

        $response->throw();

        $data = $response->json();

        return [
            'id' => (string) $data['number'],
            'url' => $data['html_url'] ?? null,
        ];
    }

    public function closeTask(Integration $integration, string $externalId): void
    {
        $creds = $integration->credentials;

        Http::withToken($creds['token'])
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->patch(self::BASE_URL."/repos/{$creds['owner']}/{$creds['repo']}/issues/{$externalId}", [
                'state' => 'closed',
                'state_reason' => 'completed',
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

        $signature = $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhookStatus(Request $request): string
    {
        return $request->input('issue.state', '');
    }

    public function parseWebhookExternalId(Request $request): string
    {
        return (string) $request->input('issue.number', '');
    }

    public function testConnection(Integration $integration): array
    {
        $creds = $integration->credentials;

        $response = Http::withToken($creds['token'])
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get(self::BASE_URL."/repos/{$creds['owner']}/{$creds['repo']}");

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Connected to GitHub successfully.'];
        }

        return ['ok' => false, 'message' => $response->json('message', 'Connection failed.')];
    }
}
