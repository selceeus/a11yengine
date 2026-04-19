<?php

use App\Domain\Integrations\Providers\MondayProvider;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->provider = new MondayProvider;

    $this->integration = Integration::factory()->make([
        'provider' => IntegrationProvider::Monday,
        'credentials' => [
            'api_token' => 'test-monday-token',
            'board_id' => '123456789',
        ],
    ]);

    $this->issue = Issue::factory()->make([
        'rule_key' => 'wcag-1-1-1',
        'description' => 'Images must have alt text',
        'page_url' => 'https://example.com/page',
        'wcag_criteria' => '1.1.1',
        'help_url' => 'https://dequeuniversity.com/rules/axe/wcag-1-1-1',
    ]);
});

// ── createTask ────────────────────────────────────────────────────────────────

it('Monday: creates a task via the GraphQL API and returns id and url', function (): void {
    Http::fake([
        'api.monday.com/v2' => Http::sequence()
            ->push(['data' => ['create_item' => ['id' => '111222333']]], 200)
            ->push(['data' => ['change_simple_column_value' => ['id' => '111222333']]], 200),
    ]);

    $result = $this->provider->createTask($this->integration, $this->issue);

    expect($result['id'])->toBe('111222333')
        ->and($result['url'])->toContain('111222333');
});

it('Monday: throws when the createTask API call fails', function (): void {
    Http::fake([
        'api.monday.com/v2' => Http::response('Internal Server Error', 500),
    ]);

    expect(fn () => $this->provider->createTask($this->integration, $this->issue))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});

// ── closeTask ─────────────────────────────────────────────────────────────────

it('Monday: updates the status column to Done via GraphQL', function (): void {
    Http::fake([
        'api.monday.com/v2' => Http::response(['data' => ['change_simple_column_value' => ['id' => '111']]], 200),
    ]);

    $this->provider->closeTask($this->integration, '111222333');

    Http::assertSentCount(1);
});

// ── verifyWebhook ─────────────────────────────────────────────────────────────

it('Monday: always returns true for webhook verification', function (): void {
    $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');

    expect($this->provider->verifyWebhook($this->integration, $request))->toBeTrue();
});

// ── parseWebhookStatus ────────────────────────────────────────────────────────

it('Monday: extracts the status label from the webhook payload', function (): void {
    $request = new Request;
    $request->merge(['event' => ['columnValues' => ['status' => ['label' => 'Done']]]]);

    expect($this->provider->parseWebhookStatus($request))->toBe('Done');
});

it('Monday: returns an empty string when the status label is missing', function (): void {
    $request = new Request;

    expect($this->provider->parseWebhookStatus($request))->toBe('');
});

// ── parseWebhookExternalId ────────────────────────────────────────────────────

it('Monday: extracts the pulse id from the webhook payload', function (): void {
    $request = new Request;
    $request->merge(['event' => ['pulseId' => 111222333]]);

    expect($this->provider->parseWebhookExternalId($request))->toBe('111222333');
});

it('Monday: returns an empty string when the pulse id is missing', function (): void {
    $request = new Request;

    expect($this->provider->parseWebhookExternalId($request))->toBe('');
});
