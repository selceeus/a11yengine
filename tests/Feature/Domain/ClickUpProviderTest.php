<?php

use App\Domain\Integrations\Providers\ClickUpProvider;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->provider = new ClickUpProvider;

    $this->integration = Integration::factory()->make([
        'provider' => IntegrationProvider::ClickUp,
        'credentials' => [
            'api_token' => 'pk_test_clickup_token',
            'list_id' => '987654321',
        ],
    ]);

    $this->issue = Issue::factory()->make([
        'rule_key' => 'color-contrast',
        'description' => 'Elements must have sufficient color contrast',
        'page_url' => 'https://example.com/about',
        'wcag_criteria' => '1.4.3',
        'help_url' => 'https://dequeuniversity.com/rules/axe/color-contrast',
    ]);
});

// ── createTask ────────────────────────────────────────────────────────────────

it('ClickUp: creates a task and returns id and url', function (): void {
    Http::fake([
        'api.clickup.com/*' => Http::response([
            'id' => 'abc123',
            'url' => 'https://app.clickup.com/t/abc123',
        ], 200),
    ]);

    $result = $this->provider->createTask($this->integration, $this->issue);

    expect($result['id'])->toBe('abc123')
        ->and($result['url'])->toBe('https://app.clickup.com/t/abc123');
});

it('ClickUp: throws when the createTask API call fails', function (): void {
    Http::fake([
        'api.clickup.com/*' => Http::response(['err' => 'Rate limit'], 429),
    ]);

    expect(fn () => $this->provider->createTask($this->integration, $this->issue))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});

// ── closeTask ─────────────────────────────────────────────────────────────────

it('ClickUp: sets task status to complete', function (): void {
    Http::fake([
        'api.clickup.com/*' => Http::response(['id' => 'abc123', 'status' => ['status' => 'complete']], 200),
    ]);

    $this->provider->closeTask($this->integration, 'abc123');

    Http::assertSentCount(1);
});

// ── verifyWebhook ─────────────────────────────────────────────────────────────

it('ClickUp: always returns true for webhook verification', function (): void {
    $request = new Request;

    expect($this->provider->verifyWebhook($this->integration, $request))->toBeTrue();
});

// ── parseWebhookStatus ────────────────────────────────────────────────────────

it('ClickUp: extracts task status from the webhook payload', function (): void {
    $request = new Request;
    $request->merge(['task_status' => ['status' => 'complete']]);

    expect($this->provider->parseWebhookStatus($request))->toBe('complete');
});

it('ClickUp: returns empty string when status is missing', function (): void {
    $request = new Request;

    expect($this->provider->parseWebhookStatus($request))->toBe('');
});

// ── parseWebhookExternalId ────────────────────────────────────────────────────

it('ClickUp: extracts task_id from the webhook payload', function (): void {
    $request = new Request;
    $request->merge(['task_id' => 'abc123']);

    expect($this->provider->parseWebhookExternalId($request))->toBe('abc123');
});

it('ClickUp: returns empty string when task_id is missing', function (): void {
    $request = new Request;

    expect($this->provider->parseWebhookExternalId($request))->toBe('');
});
