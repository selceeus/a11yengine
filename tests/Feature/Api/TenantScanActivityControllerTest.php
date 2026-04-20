<?php

use App\Enums\ApiKeyScope;
use App\Enums\ScanStatus;
use App\Models\Agency;
use App\Models\ApiKey;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;

// ── Helpers ───────────────────────────────────────────────────────────────────

function createScanActivityApiKey(Agency $agency): array
{
    $user = User::factory()->create();
    $token = ApiKey::generateToken();

    ApiKey::withoutGlobalScopes()->create([
        'agency_id' => $agency->id,
        'created_by' => $user->id,
        'name' => 'Scan Activity Test Key',
        'key_prefix' => substr($token['plaintext'], 0, 12).'...',
        'token_hash' => $token['hash'],
        'scopes' => [ApiKeyScope::ScansRead->value],
    ]);

    return $token;
}

// ── GET /api/{tenant}/scan-activity ──────────────────────────────────────────

it('Tenant scan activity: returns 401 with no API key', function (): void {
    $agency = Agency::factory()->create();

    $this->getJson("/api/{$agency->slug}/scan-activity")
        ->assertUnauthorized();
});

it('Tenant scan activity: returns 401 with an invalid API key', function (): void {
    $agency = Agency::factory()->create();

    $this->getJson("/api/{$agency->slug}/scan-activity", [
        'Authorization' => 'Bearer cbda_invalid_key',
    ])->assertUnauthorized();
});

it('Tenant scan activity: returns 403 when API key lacks scans:read scope', function (): void {
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

    $this->getJson("/api/{$agency->slug}/scan-activity", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertForbidden();
});

it('Tenant scan activity: returns 404 for an unknown agency slug', function (): void {
    $agency = Agency::factory()->create();
    $token = createScanActivityApiKey($agency);

    $this->getJson('/api/no-such-agency/scan-activity', [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertNotFound();
});

it('Tenant scan activity: returns 30 days of data with zeros when no scans exist', function (): void {
    $agency = Agency::factory()->create();
    $token = createScanActivityApiKey($agency);

    $response = $this->getJson("/api/{$agency->slug}/scan-activity", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertOk();

    $days = $response->json('days');
    expect($days)->toHaveCount(30);
    expect($days[0])->toHaveKeys(['date', 'scans', 'violations']);
    expect(array_sum(array_column($days, 'scans')))->toBe(0);
});

it('Tenant scan activity: includes completed scan counts in the correct day bucket', function (): void {
    $agency = Agency::factory()->create();
    $token = createScanActivityApiKey($agency);
    $org = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->create(['agency_id' => $agency->id, 'organization_id' => $org->id]);

    Scan::factory()->create([
        'agency_id' => $agency->id,
        'property_id' => $property->id,
        'status' => ScanStatus::Completed,
        'completed_at' => now()->subDays(2),
        'total_violations' => 5,
    ]);

    $response = $this->getJson("/api/{$agency->slug}/scan-activity", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertOk();

    $totalScans = array_sum(array_column($response->json('days'), 'scans'));
    expect($totalScans)->toBe(1);

    $totalViolations = array_sum(array_column($response->json('days'), 'violations'));
    expect($totalViolations)->toBe(5);
});

it('Tenant scan activity: does not include scans from another agency', function (): void {
    $agency = Agency::factory()->create();
    $other = Agency::factory()->create();
    $token = createScanActivityApiKey($agency);
    $org = Organization::factory()->create(['agency_id' => $other->id]);
    $property = Property::factory()->create(['agency_id' => $other->id, 'organization_id' => $org->id]);

    Scan::factory()->create([
        'agency_id' => $other->id,
        'property_id' => $property->id,
        'status' => ScanStatus::Completed,
        'completed_at' => now()->subDays(1),
        'total_violations' => 10,
    ]);

    $response = $this->getJson("/api/{$agency->slug}/scan-activity", [
        'Authorization' => 'Bearer '.$token['plaintext'],
    ])->assertOk();

    $totalScans = array_sum(array_column($response->json('days'), 'scans'));
    expect($totalScans)->toBe(0);
});
