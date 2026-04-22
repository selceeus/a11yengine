<?php

use App\Domain\Integrations\Providers\WrikeProvider;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->provider = new WrikeProvider;

    $this->integration = Integration::factory()->make([
        'provider' => IntegrationProvider::Wrike,
        'credentials' => [
            'access_token' => 'test-wrike-token',
            'folder_id' => 'IEUE2OOY',
        ],
        'settings' => [
            'wrike_webhook_id' => 'WEBHOOK123',
            'webhook_secret' => 'super-secret-key',
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

it('Wrike: creates a task in the folder and returns id and url', function (): void {
    Http::fake([
        'www.wrike.com/api/v4/folders/*/tasks' => Http::response([
            'data' => [[
                'id' => 'TASK_ABC',
                'permalink' => 'https://www.wrike.com/open.htm?id=TASK_ABC',
            ]],
        ], 200),
    ]);

    $result = $this->provider->createTask($this->integration, $this->issue);

    expect($result['id'])->toBe('TASK_ABC')
        ->and($result['url'])->toBe('https://www.wrike.com/open.htm?id=TASK_ABC');
});

it('Wrike: throws when the createTask API call fails', function (): void {
    Http::fake([
        'www.wrike.com/api/v4/folders/*/tasks' => Http::response('Internal Server Error', 500),
    ]);

    expect(fn () => $this->provider->createTask($this->integration, $this->issue))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});

// ── closeTask ─────────────────────────────────────────────────────────────────

it('Wrike: sends a PUT to mark the task as Completed', function (): void {
    Http::fake([
        'www.wrike.com/api/v4/tasks/*' => Http::response(['data' => [['id' => 'TASK_ABC']]], 200),
    ]);

    $this->provider->closeTask($this->integration, 'TASK_ABC');

    Http::assertSentCount(1);
});

// ── verifyWebhook ─────────────────────────────────────────────────────────────

it('Wrike: returns true for a valid webhook signature', function (): void {
    $secret = 'super-secret-key';
    $body = json_encode([['eventType' => 'TaskStatusChanged', 'taskId' => 'TASK_ABC']]);
    $signature = base64_encode(hash_hmac('sha256', $body, $secret, true));

    $request = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X_WRIKE_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    expect($this->provider->verifyWebhook($this->integration, $request))->toBeTrue();
});

it('Wrike: returns false for an invalid webhook signature', function (): void {
    $body = json_encode([['eventType' => 'TaskStatusChanged', 'taskId' => 'TASK_ABC']]);

    $request = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X_WRIKE_SIGNATURE' => 'invalid-signature',
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    expect($this->provider->verifyWebhook($this->integration, $request))->toBeFalse();
});

it('Wrike: returns false when the webhook secret is not set', function (): void {
    $integration = Integration::factory()->make([
        'provider' => IntegrationProvider::Wrike,
        'credentials' => ['access_token' => 'test-token', 'folder_id' => 'IEUE2OOY'],
        'settings' => null,
    ]);

    $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');

    expect($this->provider->verifyWebhook($integration, $request))->toBeFalse();
});

// ── parseWebhookStatus ────────────────────────────────────────────────────────

it('Wrike: maps TaskFinished event to completed status', function (): void {
    $request = new Request;
    $request->merge([['eventType' => 'TaskFinished', 'taskId' => 'TASK_ABC']]);

    expect($this->provider->parseWebhookStatus($request))->toBe('completed');
});

it('Wrike: returns lowercase event type for non-terminal events', function (): void {
    $request = new Request;
    $request->merge([['eventType' => 'TaskStatusChanged', 'taskId' => 'TASK_ABC']]);

    expect($this->provider->parseWebhookStatus($request))->toBe('taskstatuschanged');
});

it('Wrike: returns empty string when eventType is missing', function (): void {
    $request = new Request;

    expect($this->provider->parseWebhookStatus($request))->toBe('');
});

// ── parseWebhookExternalId ────────────────────────────────────────────────────

it('Wrike: extracts the task id from the webhook payload', function (): void {
    $request = new Request;
    $request->merge([['eventType' => 'TaskStatusChanged', 'taskId' => 'TASK_ABC']]);

    expect($this->provider->parseWebhookExternalId($request))->toBe('TASK_ABC');
});

it('Wrike: returns empty string when taskId is missing', function (): void {
    $request = new Request;

    expect($this->provider->parseWebhookExternalId($request))->toBe('');
});

// ── testConnection ────────────────────────────────────────────────────────────

it('Wrike: returns ok true on successful connection', function (): void {
    Http::fake([
        'www.wrike.com/api/v4/account' => Http::response(['data' => [['id' => 'ACCOUNT123']]], 200),
    ]);

    $result = $this->provider->testConnection($this->integration);

    expect($result['ok'])->toBeTrue()
        ->and($result['message'])->toContain('Connected to Wrike');
});

it('Wrike: returns ok false when connection fails', function (): void {
    Http::fake([
        'www.wrike.com/api/v4/account' => Http::response(['error' => 'Not Authorized', 'errorDescription' => 'Invalid token.'], 401),
    ]);

    $result = $this->provider->testConnection($this->integration);

    expect($result['ok'])->toBeFalse();
});
