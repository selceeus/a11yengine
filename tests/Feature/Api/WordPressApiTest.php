<?php

use App\Enums\ApiKeyScope;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\ApiKey;
use App\Models\Issue;
use App\Models\Property;
use Illuminate\Support\Facades\Queue;

// ── Helpers ───────────────────────────────────────────────────────────────────

function createWordPressApiKey(Agency $agency): array
{
    $token = ApiKey::generateToken();

    ApiKey::withoutGlobalScopes()->create([
        'agency_id' => $agency->id,
        'name' => 'WordPress Test Key',
        'key_prefix' => substr($token['plaintext'], 0, 12).'...',
        'token_hash' => $token['hash'],
        'scopes' => [ApiKeyScope::WordPress->value],
    ]);

    return $token;
}

// ── GET /api/wordpress/properties ────────────────────────────────────────────

it('WordPress properties: returns agency properties for a valid API key', function (): void {
    $agency = Agency::factory()->create();
    $token = createWordPressApiKey($agency);
    Property::factory()->count(2)->create(['agency_id' => $agency->id]);

    $this->getJson('/api/wordpress/properties', [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertOk()
        ->assertJsonStructure(['data', 'generated_at'])
        ->assertJsonCount(2, 'data');
});

it('WordPress properties: returns 401 with no token', function (): void {
    $this->getJson('/api/wordpress/properties')
        ->assertUnauthorized();
});

it('WordPress properties: returns 401 with an invalid token', function (): void {
    $this->getJson('/api/wordpress/properties', [
        'Authorization' => 'Bearer cbda_invalid_token_here',
    ])->assertUnauthorized();
});

it('WordPress properties: returns 403 when API key lacks the wordpress scope', function (): void {
    $agency = Agency::factory()->create();
    $token = ApiKey::generateToken();

    ApiKey::withoutGlobalScopes()->create([
        'agency_id' => $agency->id,
        'name' => 'Scans Key',
        'key_prefix' => substr($token['plaintext'], 0, 12).'...',
        'token_hash' => $token['hash'],
        'scopes' => [ApiKeyScope::ScansRead->value],
    ]);

    $this->getJson('/api/wordpress/properties', [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertForbidden();
});

it('WordPress properties: only returns properties for the authenticated agency', function (): void {
    $agency = Agency::factory()->create();
    $other = Agency::factory()->create();
    $token = createWordPressApiKey($agency);
    Property::factory()->count(2)->create(['agency_id' => $agency->id]);
    Property::factory()->count(3)->create(['agency_id' => $other->id]);

    $response = $this->getJson('/api/wordpress/properties', [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

// ── GET /api/wordpress/properties/{slug}/issues ───────────────────────────────

it('WordPress issues: returns open issues for the property', function (): void {
    $agency = Agency::factory()->create();
    $token = createWordPressApiKey($agency);
    $property = Property::factory()->create(['agency_id' => $agency->id]);
    Issue::factory()->count(3)->create([
        'agency_id' => $agency->id,
        'property_id' => $property->id,
        'status' => IssueStatus::Open,
    ]);

    $this->getJson("/api/wordpress/properties/{$property->slug}/issues", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertOk()
        ->assertJsonStructure(['property', 'data', 'generated_at'])
        ->assertJsonCount(3, 'data');
});

it('WordPress issues: excludes resolved issues', function (): void {
    $agency = Agency::factory()->create();
    $token = createWordPressApiKey($agency);
    $property = Property::factory()->create(['agency_id' => $agency->id]);
    Issue::factory()->count(2)->create([
        'agency_id' => $agency->id,
        'property_id' => $property->id,
        'status' => IssueStatus::Open,
    ]);
    Issue::factory()->count(1)->resolved()->create([
        'agency_id' => $agency->id,
        'property_id' => $property->id,
    ]);

    $response = $this->getJson("/api/wordpress/properties/{$property->slug}/issues", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('WordPress issues: returns 404 for an unknown property slug', function (): void {
    $agency = Agency::factory()->create();
    $token = createWordPressApiKey($agency);

    $this->getJson('/api/wordpress/properties/does-not-exist/issues', [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertNotFound();
});

// ── GET /api/wordpress/properties/{slug}/risk-summary ────────────────────────

it('WordPress risk-summary: returns issue counts and null score when no snapshot exists', function (): void {
    $agency = Agency::factory()->create();
    $token = createWordPressApiKey($agency);
    $property = Property::factory()->create(['agency_id' => $agency->id]);
    Issue::factory()->create([
        'agency_id' => $agency->id,
        'property_id' => $property->id,
        'severity' => IssueSeverity::Critical,
        'status' => IssueStatus::Open,
    ]);

    $this->getJson("/api/wordpress/properties/{$property->slug}/risk-summary", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertOk()
        ->assertJsonPath('risk_score', null)
        ->assertJsonPath('issue_counts.critical', 1)
        ->assertJsonPath('issue_counts.total', 1);
});

it('WordPress risk-summary: returns 404 for an unknown property slug', function (): void {
    $agency = Agency::factory()->create();
    $token = createWordPressApiKey($agency);

    $this->getJson('/api/wordpress/properties/no-such-property/risk-summary', [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertNotFound();
});

// ── POST /api/wordpress/properties/{slug}/scans ───────────────────────────────

it('WordPress scans: queues a scan and returns 201 with scan data', function (): void {
    Queue::fake();

    $agency = Agency::factory()->create();
    $token = createWordPressApiKey($agency);
    $property = Property::factory()->create(['agency_id' => $agency->id]);

    $this->postJson("/api/wordpress/properties/{$property->slug}/scans", [], [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertCreated()
        ->assertJsonStructure(['scan_id', 'property', 'status', 'message', 'created_at'])
        ->assertJsonPath('status', 'pending');

    Queue::assertCount(1);
});

it('WordPress scans: returns 404 for an unknown property slug', function (): void {
    Queue::fake();

    $agency = Agency::factory()->create();
    $token = createWordPressApiKey($agency);

    $this->postJson('/api/wordpress/properties/no-such-property/scans', [], [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertNotFound();
});
