<?php

use App\Domain\Integrations\Providers\BasecampProvider;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->provider = new BasecampProvider;

    $this->integration = Integration::factory()->make([
        'provider' => IntegrationProvider::Basecamp,
        'credentials' => [
            'access_token' => 'basecamp-bearer-token',
            'account_id' => '1234567',
            'project_id' => '9876543',
        ],
    ]);

    $this->issue = Issue::factory()->make([
        'rule_key' => 'frame-title',
        'description' => 'Frames must have an accessible name',
        'page_url' => 'https://example.com/embed',
        'wcag_criteria' => '2.4.1',
        'help_url' => 'https://dequeuniversity.com/rules/axe/frame-title',
    ]);
});

// ── createTask ────────────────────────────────────────────────────────────────

it('Basecamp: creates a todo and returns id and url', function (): void {
    Http::fake([
        '3.basecampapi.com/*/todolists.json' => Http::response([
            ['id' => 555, 'name' => 'Backlog'],
        ], 200),
        '3.basecampapi.com/*/todos.json' => Http::response([
            'id' => 999,
            'app_url' => 'https://3.basecamp.com/1234567/buckets/9876543/todos/999',
        ], 200),
    ]);

    $result = $this->provider->createTask($this->integration, $this->issue);

    expect($result['id'])->toBe('999')
        ->and($result['url'])->toContain('999');
});

it('Basecamp: sends the required User-Agent header', function (): void {
    Http::fake([
        '3.basecampapi.com/*/todolists.json' => Http::response([['id' => 555]], 200),
        '3.basecampapi.com/*/todos.json' => Http::response(['id' => 1, 'app_url' => null], 200),
    ]);

    $this->provider->createTask($this->integration, $this->issue);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        return $request->hasHeader('User-Agent');
    });
});

it('Basecamp: throws when no todolists are found in the project', function (): void {
    Http::fake([
        '3.basecampapi.com/*' => Http::response([], 200),
    ]);

    expect(fn () => $this->provider->createTask($this->integration, $this->issue))
        ->toThrow(\RuntimeException::class);
});

it('Basecamp: throws when the todolists API call fails', function (): void {
    Http::fake([
        '3.basecampapi.com/*' => Http::response('Forbidden', 403),
    ]);

    expect(fn () => $this->provider->createTask($this->integration, $this->issue))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});

// ── closeTask ─────────────────────────────────────────────────────────────────

it('Basecamp: posts to the completion endpoint to close the todo', function (): void {
    Http::fake([
        '3.basecampapi.com/*/completion.json' => Http::response('', 204),
    ]);

    $this->provider->closeTask($this->integration, '999');

    Http::assertSentCount(1);
});

// ── verifyWebhook ─────────────────────────────────────────────────────────────

it('Basecamp: always returns true for webhook verification', function (): void {
    $request = new Request;

    expect($this->provider->verifyWebhook($this->integration, $request))->toBeTrue();
});

// ── parseWebhookStatus ────────────────────────────────────────────────────────

it('Basecamp: extracts the kind field from the webhook payload', function (): void {
    $request = new Request;
    $request->merge(['kind' => 'todo_completed']);

    expect($this->provider->parseWebhookStatus($request))->toBe('todo_completed');
});

it('Basecamp: returns empty string when kind is missing', function (): void {
    $request = new Request;

    expect($this->provider->parseWebhookStatus($request))->toBe('');
});

// ── parseWebhookExternalId ────────────────────────────────────────────────────

it('Basecamp: extracts the recording id from the webhook payload', function (): void {
    $request = new Request;
    $request->merge(['recording' => ['id' => 999]]);

    expect($this->provider->parseWebhookExternalId($request))->toBe('999');
});

it('Basecamp: returns empty string when recording id is missing', function (): void {
    $request = new Request;

    expect($this->provider->parseWebhookExternalId($request))->toBe('');
});
