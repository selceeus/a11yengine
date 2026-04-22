<?php

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationStatus;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Integration;
use App\Models\Issue;
use App\Models\IssueLink;
use App\Models\Organization;
use App\Models\Property;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function wrikeSignature(string $body, string $secret): string
{
    return base64_encode(hash_hmac('sha256', $body, $secret, true));
}

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);

    $this->integration = Integration::withoutGlobalScopes()->create([
        'agency_id' => $this->agency->id,
        'provider' => IntegrationProvider::Wrike,
        'name' => 'Wrike Test',
        'credentials' => ['access_token' => 'test-token', 'folder_id' => 'FOLDER123'],
        'settings' => ['wrike_webhook_id' => 'WEBHOOK123', 'webhook_secret' => 'test-secret'],
        'status' => IntegrationStatus::Active,
    ]);

    $this->issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
    ]);

    $this->issueLink = IssueLink::create([
        'issue_id' => $this->issue->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'TASK_ABC',
        'external_url' => 'https://www.wrike.com/open.htm?id=TASK_ABC',
        'external_status' => 'Active',
    ]);
});

// ── Valid webhook ─────────────────────────────────────────────────────────────

it('updates the IssueLink external_status on a valid Wrike webhook', function (): void {
    $payload = json_encode([['eventType' => 'TaskStatusChanged', 'taskId' => 'TASK_ABC']]);
    $signature = wrikeSignature($payload, 'test-secret');

    $this->postJson(
        route('api.webhooks.integrations', $this->integration),
        json_decode($payload, true),
        ['X-Wrike-Signature' => $signature]
    )->assertNoContent();

    expect($this->issueLink->fresh()->external_status)->toBe('taskstatuschanged');
});

it('resolves the linked Issue when TaskFinished event is received', function (): void {
    $payload = json_encode([['eventType' => 'TaskFinished', 'taskId' => 'TASK_ABC']]);
    $signature = wrikeSignature($payload, 'test-secret');

    $this->postJson(
        route('api.webhooks.integrations', $this->integration),
        json_decode($payload, true),
        ['X-Wrike-Signature' => $signature]
    )->assertNoContent();

    expect($this->issue->fresh()->status)->toBe(IssueStatus::Resolved);
});

it('returns 204 and ignores events for unknown task ids', function (): void {
    $payload = json_encode([['eventType' => 'TaskStatusChanged', 'taskId' => 'UNKNOWN_TASK']]);
    $signature = wrikeSignature($payload, 'test-secret');

    $this->postJson(
        route('api.webhooks.integrations', $this->integration),
        json_decode($payload, true),
        ['X-Wrike-Signature' => $signature]
    )->assertNoContent();

    expect($this->issue->fresh()->status)->toBe(IssueStatus::Open);
});

// ── Invalid signature ─────────────────────────────────────────────────────────

it('returns 401 when the Wrike signature is invalid', function (): void {
    $payload = json_encode([['eventType' => 'TaskStatusChanged', 'taskId' => 'TASK_ABC']]);

    $this->postJson(
        route('api.webhooks.integrations', $this->integration),
        json_decode($payload, true),
        ['X-Wrike-Signature' => 'bad-signature']
    )->assertUnauthorized();
});

it('returns 401 when the X-Wrike-Signature header is absent', function (): void {
    $payload = json_encode([['eventType' => 'TaskStatusChanged', 'taskId' => 'TASK_ABC']]);

    $this->postJson(
        route('api.webhooks.integrations', $this->integration),
        json_decode($payload, true)
    )->assertUnauthorized();
});
