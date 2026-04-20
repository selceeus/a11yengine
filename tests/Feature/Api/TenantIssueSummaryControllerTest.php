<?php

use App\Enums\ApiKeyScope;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\ApiKey;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;

// ── Helpers ───────────────────────────────────────────────────────────────────

function createIssueSummaryApiKey(Agency $agency): array
{
    $user = User::factory()->create();
    $token = ApiKey::generateToken();

    ApiKey::withoutGlobalScopes()->create([
        'agency_id' => $agency->id,
        'created_by' => $user->id,
        'name' => 'Issue Summary Test Key',
        'key_prefix' => substr($token['plaintext'], 0, 12).'...',
        'token_hash' => $token['hash'],
        'scopes' => [ApiKeyScope::ScansRead->value],
    ]);

    return $token;
}

// ── GET /api/{tenant}/issues ──────────────────────────────────────────────────

it('Tenant issue summary: returns 401 with no API key', function (): void {
    $agency = Agency::factory()->create();

    $this->getJson("/api/{$agency->slug}/issues")
        ->assertUnauthorized();
});

it('Tenant issue summary: returns 401 with an invalid API key', function (): void {
    $agency = Agency::factory()->create();

    $this->getJson("/api/{$agency->slug}/issues", [
        'Authorization' => 'Bearer cbda_invalid_key',
    ])->assertUnauthorized();
});

it('Tenant issue summary: returns 403 when API key lacks scans:read scope', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create();
    $token = ApiKey::generateToken();

    ApiKey::withoutGlobalScopes()->create([
        'agency_id' => $agency->id,
        'created_by' => $user->id,
        'name' => 'Wrong Scope Key',
        'key_prefix' => substr($token['plaintext'], 0, 12).'...',
        'token_hash' => $token['hash'],
        'scopes' => [ApiKeyScope::WordPress->value],
    ]);

    $this->getJson("/api/{$agency->slug}/issues", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertForbidden();
});

it('Tenant issue summary: returns 404 for an unknown agency slug', function (): void {
    $agency = Agency::factory()->create();
    $token = createIssueSummaryApiKey($agency);

    $this->getJson('/api/no-such-agency/issues', [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertNotFound();
});

it('Tenant issue summary: returns all-zero counts when no issues exist', function (): void {
    $agency = Agency::factory()->create();
    $token = createIssueSummaryApiKey($agency);

    $this->getJson("/api/{$agency->slug}/issues", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])
        ->assertOk()
        ->assertJson(['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'total' => 0])
        ->assertJsonStructure(['critical', 'high', 'medium', 'low', 'total', 'generated_at']);
});

it('Tenant issue summary: counts active issues by severity', function (): void {
    $agency = Agency::factory()->create();
    $token = createIssueSummaryApiKey($agency);
    $org = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->create(['agency_id' => $agency->id, 'organization_id' => $org->id]);

    Issue::factory()->create([
        'agency_id' => $agency->id,
        'property_id' => $property->id,
        'severity' => IssueSeverity::Critical,
        'status' => IssueStatus::Open,
    ]);
    Issue::factory()->create([
        'agency_id' => $agency->id,
        'property_id' => $property->id,
        'severity' => IssueSeverity::High,
        'status' => IssueStatus::InProgress,
    ]);
    // Resolved issue — should not be counted
    Issue::factory()->create([
        'agency_id' => $agency->id,
        'property_id' => $property->id,
        'severity' => IssueSeverity::Low,
        'status' => IssueStatus::Resolved,
    ]);

    $this->getJson("/api/{$agency->slug}/issues", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])
        ->assertOk()
        ->assertJsonPath('critical', 1)
        ->assertJsonPath('high', 1)
        ->assertJsonPath('low', 0)
        ->assertJsonPath('total', 2);
});

it('Tenant issue summary: does not include issues from another agency', function (): void {
    $agency = Agency::factory()->create();
    $other = Agency::factory()->create();
    $token = createIssueSummaryApiKey($agency);
    $org = Organization::factory()->create(['agency_id' => $other->id]);
    $property = Property::factory()->create(['agency_id' => $other->id, 'organization_id' => $org->id]);

    Issue::factory()->create([
        'agency_id' => $other->id,
        'property_id' => $property->id,
        'severity' => IssueSeverity::Critical,
        'status' => IssueStatus::Open,
    ]);

    $this->getJson("/api/{$agency->slug}/issues", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])
        ->assertOk()
        ->assertJsonPath('total', 0);
});
