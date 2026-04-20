<?php

use App\Enums\ApiKeyScope;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\ScanStatus;
use App\Models\Agency;
use App\Models\AgencyRiskSnapshot;
use App\Models\ApiKey;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;

// ── Helpers ───────────────────────────────────────────────────────────────────

function createGovernanceSummaryApiKey(Agency $agency): array
{
    $user = User::factory()->create();
    $token = ApiKey::generateToken();

    ApiKey::withoutGlobalScopes()->create([
        'agency_id' => $agency->id,
        'created_by' => $user->id,
        'name' => 'Governance Summary Test Key',
        'key_prefix' => substr($token['plaintext'], 0, 12).'...',
        'token_hash' => $token['hash'],
        'scopes' => [ApiKeyScope::ScansRead->value],
    ]);

    return $token;
}

// ── GET /api/{tenant}/governance-summary ─────────────────────────────────────

it('Tenant governance summary: returns 401 with no API key', function (): void {
    $agency = Agency::factory()->create();

    $this->getJson("/api/{$agency->slug}/governance-summary")
        ->assertUnauthorized();
});

it('Tenant governance summary: returns 401 with an invalid API key', function (): void {
    $agency = Agency::factory()->create();

    $this->getJson("/api/{$agency->slug}/governance-summary", [
        'Authorization' => 'Bearer cbda_invalid_key',
    ])->assertUnauthorized();
});

it('Tenant governance summary: returns 403 when API key lacks scans:read scope', function (): void {
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

    $this->getJson("/api/{$agency->slug}/governance-summary", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertForbidden();
});

it('Tenant governance summary: returns 404 for an unknown agency slug', function (): void {
    $agency = Agency::factory()->create();
    $token = createGovernanceSummaryApiKey($agency);

    $this->getJson('/api/no-such-agency/governance-summary', [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertNotFound();
});

it('Tenant governance summary: returns correct shape with zero data', function (): void {
    $agency = Agency::factory()->create();
    $token = createGovernanceSummaryApiKey($agency);

    $this->getJson("/api/{$agency->slug}/governance-summary", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])
        ->assertOk()
        ->assertJsonPath('agency_id', $agency->id)
        ->assertJsonPath('agency_name', $agency->name)
        ->assertJsonPath('open_issues', 0)
        ->assertJsonPath('total_scans', 0)
        ->assertJsonPath('organizations_count', 0)
        ->assertJsonStructure([
            'agency_id', 'agency_name', 'total_risk_score', 'risk_delta',
            'open_issues', 'severity_distribution', 'total_scans',
            'scans_last_30_days', 'total_violations', 'organizations_count', 'generated_at',
        ]);
});

it('Tenant governance summary: counts open issues and scans correctly', function (): void {
    $agency = Agency::factory()->create();
    $token = createGovernanceSummaryApiKey($agency);
    $org = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->create(['agency_id' => $agency->id, 'organization_id' => $org->id]);

    Issue::factory()->count(3)->create([
        'agency_id' => $agency->id,
        'property_id' => $property->id,
        'severity' => IssueSeverity::Critical,
        'status' => IssueStatus::Open,
    ]);
    Issue::factory()->create([
        'agency_id' => $agency->id,
        'property_id' => $property->id,
        'severity' => IssueSeverity::Low,
        'status' => IssueStatus::Resolved,
    ]);

    Scan::factory()->count(2)->create([
        'agency_id' => $agency->id,
        'property_id' => $property->id,
        'status' => ScanStatus::Completed,
        'completed_at' => now()->subDays(5),
        'total_violations' => 4,
    ]);

    $this->getJson("/api/{$agency->slug}/governance-summary", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])
        ->assertOk()
        ->assertJsonPath('open_issues', 3)
        ->assertJsonPath('total_scans', 2)
        ->assertJsonPath('scans_last_30_days', 2)
        ->assertJsonPath('severity_distribution.critical', 3)
        ->assertJsonPath('organizations_count', 1);
});

it('Tenant governance summary: computes risk_delta from last two snapshots', function (): void {
    $agency = Agency::factory()->create();
    $token = createGovernanceSummaryApiKey($agency);

    AgencyRiskSnapshot::factory()->create([
        'agency_id' => $agency->id,
        'risk_score' => 100,
        'snapshot_date' => now()->subDays(2)->toDateString(),
    ]);
    AgencyRiskSnapshot::factory()->create([
        'agency_id' => $agency->id,
        'risk_score' => 80,
        'snapshot_date' => now()->subDays(1)->toDateString(),
    ]);

    $this->getJson("/api/{$agency->slug}/governance-summary", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])
        ->assertOk()
        ->assertJsonPath('risk_delta', -20);
});

it('Tenant governance summary: does not leak data from another agency', function (): void {
    $agency = Agency::factory()->create();
    $other = Agency::factory()->create();
    $token = createGovernanceSummaryApiKey($agency);
    $org = Organization::factory()->create(['agency_id' => $other->id]);
    $property = Property::factory()->create(['agency_id' => $other->id, 'organization_id' => $org->id]);

    Issue::factory()->count(5)->create([
        'agency_id' => $other->id,
        'property_id' => $property->id,
        'status' => IssueStatus::Open,
    ]);

    $this->getJson("/api/{$agency->slug}/governance-summary", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])
        ->assertOk()
        ->assertJsonPath('open_issues', 0);
});
