<?php

use App\Domain\Integrations\Providers\NotionProvider;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->provider = new NotionProvider;

    $this->integration = Integration::factory()->make([
        'provider' => IntegrationProvider::Notion,
        'credentials' => [
            'integration_token' => 'secret_notion_token',
            'database_id' => 'db_abc123',
        ],
    ]);

    $this->issue = Issue::factory()->make([
        'rule_key' => 'document-title',
        'description' => 'Documents must have a title element',
        'page_url' => 'https://example.com/blog',
        'wcag_criteria' => '2.4.2',
        'help_url' => 'https://dequeuniversity.com/rules/axe/document-title',
    ]);
});

// ── createTask ────────────────────────────────────────────────────────────────

it('Notion: creates a page in the database and returns id and url', function (): void {
    Http::fake([
        'api.notion.com/*' => Http::response([
            'id' => 'page-uuid-abc-123',
            'url' => 'https://notion.so/page-uuid-abc-123',
        ], 200),
    ]);

    $result = $this->provider->createTask($this->integration, $this->issue);

    expect($result['id'])->toBe('page-uuid-abc-123')
        ->and($result['url'])->toBe('https://notion.so/page-uuid-abc-123');
});

it('Notion: sends the Notion-Version header', function (): void {
    Http::fake([
        'api.notion.com/*' => Http::response(['id' => 'page-1', 'url' => null], 200),
    ]);

    $this->provider->createTask($this->integration, $this->issue);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        return $request->hasHeader('Notion-Version');
    });
});

it('Notion: throws when the createTask API call fails', function (): void {
    Http::fake([
        'api.notion.com/*' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    expect(fn () => $this->provider->createTask($this->integration, $this->issue))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});

// ── closeTask ─────────────────────────────────────────────────────────────────

it('Notion: archives the page to close it', function (): void {
    Http::fake([
        'api.notion.com/*' => Http::response(['id' => 'page-uuid', 'archived' => true], 200),
    ]);

    $this->provider->closeTask($this->integration, 'page-uuid-abc-123');

    Http::assertSentCount(1);
});

// ── verifyWebhook ─────────────────────────────────────────────────────────────

it('Notion: always returns true for webhook verification', function (): void {
    $request = new Request;

    expect($this->provider->verifyWebhook($this->integration, $request))->toBeTrue();
});

// ── parseWebhookStatus ────────────────────────────────────────────────────────

it('Notion: extracts the status property name from the webhook payload', function (): void {
    $request = new Request;
    $request->merge([
        'data' => ['object' => ['properties' => ['Status' => ['status' => ['name' => 'Done']]]]],
    ]);

    expect($this->provider->parseWebhookStatus($request))->toBe('Done');
});

it('Notion: returns empty string when status is missing', function (): void {
    $request = new Request;

    expect($this->provider->parseWebhookStatus($request))->toBe('');
});

// ── parseWebhookExternalId ────────────────────────────────────────────────────

it('Notion: extracts the page id from the webhook payload', function (): void {
    $request = new Request;
    $request->merge(['data' => ['object' => ['id' => 'page-uuid-abc-123']]]);

    expect($this->provider->parseWebhookExternalId($request))->toBe('page-uuid-abc-123');
});

it('Notion: returns empty string when page id is missing', function (): void {
    $request = new Request;

    expect($this->provider->parseWebhookExternalId($request))->toBe('');
});
