<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Contracts\ProjectManagementProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AzureDevOpsProvider implements ProjectManagementProvider
{
    private const API_VERSION = '7.1';

    public function createTask(Integration $integration, Issue $issue): array
    {
        $creds = $integration->credentials;
        $org = rawurlencode($creds['organization']);
        $project = rawurlencode($creds['project']);
        $url = "https://dev.azure.com/{$org}/{$project}/_apis/wit/workitems/\$Task?api-version=".self::API_VERSION;

        $body = [
            ['op' => 'add', 'path' => '/fields/System.Title', 'value' => "[A11y] {$issue->rule_key}: {$issue->description}"],
            ['op' => 'add', 'path' => '/fields/System.Description', 'value' => implode('<br>', [
                "Page: {$issue->page_url}",
                "WCAG: {$issue->wcag_criteria}",
                "Severity: {$issue->severity->value}",
                "Help: <a href=\"{$issue->help_url}\">{$issue->help_url}</a>",
            ])],
            ['op' => 'add', 'path' => '/fields/Microsoft.VSTS.Common.Priority', 'value' => $this->mapSeverity($issue->severity->value)],
        ];

        $response = Http::withBasicAuth('', $creds['pat'])
            ->withHeaders(['Content-Type' => 'application/json-patch+json'])
            ->post($url, $body);

        $response->throw();

        $task = $response->json();

        return [
            'id' => (string) $task['id'],
            'url' => $task['_links']['html']['href'] ?? null,
        ];
    }

    public function closeTask(Integration $integration, string $externalId): void
    {
        $creds = $integration->credentials;
        $org = rawurlencode($creds['organization']);
        $project = rawurlencode($creds['project']);
        $url = "https://dev.azure.com/{$org}/{$project}/_apis/wit/workitems/{$externalId}?api-version=".self::API_VERSION;

        Http::withBasicAuth('', $creds['pat'])
            ->withHeaders(['Content-Type' => 'application/json-patch+json'])
            ->patch($url, [
                ['op' => 'add', 'path' => '/fields/System.State', 'value' => 'Closed'],
            ])
            ->throw();
    }

    public function verifyWebhook(Integration $integration, Request $request): bool
    {
        $creds = $integration->credentials;
        $webhookPassword = $creds['webhook_password'] ?? null;

        if (empty($webhookPassword)) {
            return true;
        }

        $authHeader = $request->header('Authorization', '');

        if (! str_starts_with($authHeader, 'Basic ')) {
            return false;
        }

        $decoded = base64_decode(substr($authHeader, 6), true);
        $parts = explode(':', (string) $decoded, 2);
        $password = $parts[1] ?? '';

        return hash_equals($webhookPassword, $password);
    }

    public function parseWebhookStatus(Request $request): string
    {
        $fields = $request->input('resource.fields', []);

        return $fields['System.State']['newValue'] ?? '';
    }

    public function parseWebhookExternalId(Request $request): string
    {
        return (string) $request->input('resource.workItemId', '');
    }

    public function testConnection(Integration $integration): array
    {
        $creds = $integration->credentials;
        $org = rawurlencode($creds['organization']);
        $url = "https://dev.azure.com/{$org}/_apis/projects?api-version=".self::API_VERSION;

        $response = Http::withBasicAuth('', $creds['pat'])->get($url);

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Connected to Azure DevOps successfully.'];
        }

        return ['ok' => false, 'message' => $response->json('message', 'Connection failed.')];
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
