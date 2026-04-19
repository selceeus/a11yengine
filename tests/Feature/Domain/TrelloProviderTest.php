<?php

use App\Domain\Integrations\Providers\TrelloProvider;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->provider = new TrelloProvider;

    $this->integration = Integration::factory()->make([
        'provider' => IntegrationProvider::Trello,
        'credentials' => [
            'api_key' => 'trello-api-key',
            'token' => 'trello-oauth-token',
            'list_id' => 'list_abc123',
        ],
    ]);

    $this->issue = Issue::factory()->make([
        'rule_key' => 'aria-required-attr',
        'description' => 'Required ARIA attributes must be provided',
        'page_url' => 'https://example.com/dashboard',
        'wcag_criteria' => '4.1.2',
        'help_url' => 'https://dequeuniversity.com/rules/axe/aria-required-attr',
    ]);
});

// ── createTask ────────────────────────────────────────────────────────────────

it('Trello: creates a card and returns id and url', function (): void {
    Http::fake([
        'api.trello.com/*' => Http::response([
            'id' => 'card_xyz789',
            'shortUrl' => 'https://trello.com/c/xyz789',
            'url' => 'https://trello.com/c/xyz789/a11y-card',
        ], 200),
    ]);

    $result = $this->provider->createTask($this->integration, $this->issue);

    expect($result['id'])->toBe('card_xyz789')
        ->and($result['url'])->toBe('https://trello.com/c/xyz789');
});

it('Trello: uses the url field when shortUrl is absent', function (): void {
    Http::fake([
        'api.trello.com/*' => Http::response([
            'id' => 'card_abc',
            'url' => 'https://trello.com/c/abc/fallback',
        ], 200),
    ]);

    $result = $this->provider->createTask($this->integration, $this->issue);

    expect($result['url'])->toBe('https://trello.com/c/abc/fallback');
});

it('Trello: throws when the createTask API call fails', function (): void {
    Http::fake([
        'api.trello.com/*' => Http::response('invalid token', 401),
    ]);

    expect(fn () => $this->provider->createTask($this->integration, $this->issue))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});

// ── closeTask ─────────────────────────────────────────────────────────────────

it('Trello: archives the card to close it', function (): void {
    Http::fake([
        'api.trello.com/*' => Http::response(['id' => 'card_xyz789', 'closed' => true], 200),
    ]);

    $this->provider->closeTask($this->integration, 'card_xyz789');

    Http::assertSentCount(1);
});

// ── verifyWebhook ─────────────────────────────────────────────────────────────

it('Trello: always returns true for webhook verification', function (): void {
    $request = new Request;

    expect($this->provider->verifyWebhook($this->integration, $request))->toBeTrue();
});

// ── parseWebhookStatus ────────────────────────────────────────────────────────

it('Trello: extracts the action type from the webhook payload', function (): void {
    $request = new Request;
    $request->merge(['action' => ['type' => 'updateCard']]);

    expect($this->provider->parseWebhookStatus($request))->toBe('updateCard');
});

it('Trello: returns empty string when action type is missing', function (): void {
    $request = new Request;

    expect($this->provider->parseWebhookStatus($request))->toBe('');
});

// ── parseWebhookExternalId ────────────────────────────────────────────────────

it('Trello: extracts the card id from the webhook payload', function (): void {
    $request = new Request;
    $request->merge(['action' => ['data' => ['card' => ['id' => 'card_xyz789']]]]);

    expect($this->provider->parseWebhookExternalId($request))->toBe('card_xyz789');
});

it('Trello: returns empty string when card id is missing', function (): void {
    $request = new Request;

    expect($this->provider->parseWebhookExternalId($request))->toBe('');
});
