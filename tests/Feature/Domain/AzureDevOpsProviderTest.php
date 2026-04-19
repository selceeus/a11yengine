<?php

use App\Domain\Integrations\Providers\AzureDevOpsProvider;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->provider = new AzureDevOpsProvider;

    $this->integration = Integration::factory()->make([
        'provider' => IntegrationProvider::AzureDevOps,
        'credentials' => [
            'pat' => 'fake-personal-access-token',
            'organization' => 'MyOrg',
            'project' => 'MyProject',
        ],
    ]);

    $this->issue = Issue::factory()->make([
        'rule_key' => 'label',
        'description' => 'Form elements must have labels',
        'page_url' => 'https://example.com/contact',
        'wcag_criteria' => '1.3.1',
        'help_url' => 'https://dequeuniversity.com/rules/axe/label',
    ]);
});

// ── createTask ────────────────────────────────────────────────────────────────

it('AzureDevOps: creates a work item and returns id and url', function (): void {
    Http::fake([
        'dev.azure.com/*' => Http::response([
            'id' => 42,
            '_links' => ['html' => ['href' => 'https://dev.azure.com/MyOrg/MyProject/_workitems/edit/42']],
        ], 200),
    ]);

    $result = $this->provider->createTask($this->integration, $this->issue);

    expect($result['id'])->toBe('42')
        ->and($result['url'])->toContain('42');
});

it('AzureDevOps: sends request with JSON Patch content type', function (): void {
    Http::fake([
        'dev.azure.com/*' => Http::response(['id' => 1, '_links' => []], 200),
    ]);

    $this->provider->createTask($this->integration, $this->issue);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        return str_contains($request->header('Content-Type')[0] ?? '', 'application/json-patch+json');
    });
});

it('AzureDevOps: throws when the createTask API call fails', function (): void {
    Http::fake([
        'dev.azure.com/*' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    expect(fn () => $this->provider->createTask($this->integration, $this->issue))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});

// ── closeTask ─────────────────────────────────────────────────────────────────

it('AzureDevOps: sets work item state to Closed', function (): void {
    Http::fake([
        'dev.azure.com/*' => Http::response(['id' => 42], 200),
    ]);

    $this->provider->closeTask($this->integration, '42');

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        $body = $request->data();

        return collect($body)->contains(fn (array $op): bool => $op['path'] === '/fields/System.State' && $op['value'] === 'Closed');
    });
});

// ── verifyWebhook ─────────────────────────────────────────────────────────────

it('AzureDevOps: always returns true for webhook verification', function (): void {
    $request = new Request;

    expect($this->provider->verifyWebhook($this->integration, $request))->toBeTrue();
});

// ── parseWebhookStatus ────────────────────────────────────────────────────────

it('AzureDevOps: extracts the new state value from the webhook payload', function (): void {
    $request = new Request;
    $request->merge(['resource' => ['fields' => ['System.State' => ['newValue' => 'Resolved']]]]);

    expect($this->provider->parseWebhookStatus($request))->toBe('Resolved');
});

// ── parseWebhookExternalId ────────────────────────────────────────────────────

it('AzureDevOps: extracts the workItemId from the webhook payload', function (): void {
    $request = new Request;
    $request->merge(['resource' => ['workItemId' => 42]]);

    expect($this->provider->parseWebhookExternalId($request))->toBe('42');
});
