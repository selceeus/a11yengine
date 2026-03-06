<?php

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\ScanStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\AgencyRiskSnapshot;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);
    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
});

// ── Authentication & Authorization ────────────────────────────────────────────

it('requires authentication', function (): void {
    $this->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertUnauthorized();
});

it('returns 403 when the user belongs to a different agency', function (): void {
    $otherAgency = Agency::factory()->create();

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $otherAgency->id))
        ->assertForbidden();
});

it('allows a super user to access any agency', function (): void {
    $superUser = User::factory()->create(['agency_id' => null]);
    $superUser->roles()->create(['role' => UserRole::SuperUser->value]);

    $otherAgency = Agency::factory()->create();

    $this->actingAs($superUser)
        ->getJson(route('api.agencies.governance-report', $otherAgency->id))
        ->assertOk();
});

it('allows any user belonging to the agency to access the report', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk();
});

// ── Response structure ────────────────────────────────────────────────────────

it('returns the correct JSON structure', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonStructure([
            'agency_id',
            'agency_name',
            'total_risk_score',
            'risk_delta',
            'open_issues',
            'severity_distribution' => ['critical', 'high', 'medium', 'low'],
            'total_scans',
            'scans_last_30_days',
            'total_violations',
            'organizations_count',
            'generated_at',
        ]);
});

it('returns the correct agency identifiers', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonFragment([
            'agency_id' => $this->agency->id,
            'agency_name' => $this->agency->name,
        ]);
});

it('generated_at is an ISO 8601 date string', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk();

    $generatedAt = $response->json('generated_at');

    expect($generatedAt)->toBeString();
    expect((bool) \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $generatedAt))->toBeTrue();
});

it('returns 404 when the agency does not exist', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', 99999))
        ->assertNotFound();
});

// ── Empty / zeroed metrics ────────────────────────────────────────────────────

it('returns zeroed metrics when the agency has no data', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJson([
            'total_risk_score' => 0,
            'risk_delta' => null,
            'open_issues' => 0,
            'severity_distribution' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0],
            'total_scans' => 0,
            'scans_last_30_days' => 0,
            'total_violations' => 0,
            'organizations_count' => 1, // the one created in beforeEach
        ]);
});

it('risk_delta is null when fewer than two snapshots exist', function (): void {
    AgencyRiskSnapshot::factory()->create([
        'agency_id' => $this->agency->id,
        'risk_score' => 100,
        'snapshot_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk();

    expect($response->json('risk_delta'))->toBeNull();
});

// ── Issue aggregation ─────────────────────────────────────────────────────────

it('counts open issues across the agency', function (): void {
    Issue::factory()->count(3)->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonFragment(['open_issues' => 3]);
});

it('excludes resolved, ignored, and false_positive issues from open_issues', function (): void {
    Issue::factory()->resolved()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Ignored,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonFragment(['open_issues' => 1]);
});

it('aggregates the total_risk_score as sum of risk_weight * occurrence_count for open issues', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'risk_weight' => 10,
        'occurrence_count' => 3,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'risk_weight' => 5,
        'occurrence_count' => 2,
    ]);

    // Resolved issue — must not be included
    Issue::factory()->resolved()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'risk_weight' => 100,
        'occurrence_count' => 10,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonFragment(['total_risk_score' => 40]); // (10*3) + (5*2)
});

it('distributes open issues correctly across severity levels', function (): void {
    foreach ([IssueSeverity::Critical, IssueSeverity::Critical, IssueSeverity::High, IssueSeverity::Low] as $severity) {
        Issue::factory()->create([
            'agency_id' => $this->agency->id,
            'organization_id' => $this->organization->id,
            'property_id' => $this->property->id,
            'status' => IssueStatus::Open,
            'severity' => $severity,
        ]);
    }

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonPath('severity_distribution.critical', 2)
        ->assertJsonPath('severity_distribution.high', 1)
        ->assertJsonPath('severity_distribution.medium', 0)
        ->assertJsonPath('severity_distribution.low', 1);
});

it('does not leak issues from a different agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    $otherProperty = Property::factory()->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
    ]);

    Issue::factory()->count(5)->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
        'property_id' => $otherProperty->id,
        'status' => IssueStatus::Open,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonFragment(['open_issues' => 0]);
});

// ── Scan aggregation ──────────────────────────────────────────────────────────

it('counts all scans including non-completed', function (): void {
    Scan::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    Scan::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => ScanStatus::Pending,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonFragment(['total_scans' => 2]);
});

it('counts only completed scans within the last 30 days', function (): void {
    // Completed within the window
    Scan::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'completed_at' => now()->subDays(10),
    ]);

    // Completed but older than 30 days
    Scan::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'completed_at' => now()->subDays(45),
    ]);

    // Pending — not counted
    Scan::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => ScanStatus::Pending,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonFragment(['scans_last_30_days' => 1]);
});

it('sums total_violations from completed scans only', function (): void {
    Scan::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'total_violations' => 50,
    ]);

    Scan::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'total_violations' => 30,
    ]);

    // Pending scan with violations — must not be counted
    Scan::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => ScanStatus::Pending,
        'total_violations' => 999,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonFragment(['total_violations' => 80]);
});

it('does not leak scans from a different agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    $otherProperty = Property::factory()->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
    ]);

    Scan::factory()->completed()->count(3)->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
        'property_id' => $otherProperty->id,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonFragment(['total_scans' => 0]);
});

// ── Risk snapshot aggregation ─────────────────────────────────────────────────

it('calculates risk_delta from the two most recent snapshots', function (): void {
    AgencyRiskSnapshot::factory()->create([
        'agency_id' => $this->agency->id,
        'risk_score' => 120,
        'snapshot_date' => now()->subDay()->toDateString(),
    ]);

    AgencyRiskSnapshot::factory()->create([
        'agency_id' => $this->agency->id,
        'risk_score' => 100,
        'snapshot_date' => now()->subDays(2)->toDateString(),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonFragment(['risk_delta' => 20]); // 120 - 100
});

it('uses the two most recent snapshots when more than two exist', function (): void {
    AgencyRiskSnapshot::factory()->create([
        'agency_id' => $this->agency->id,
        'risk_score' => 200,
        'snapshot_date' => now()->toDateString(),
    ]);

    AgencyRiskSnapshot::factory()->create([
        'agency_id' => $this->agency->id,
        'risk_score' => 180,
        'snapshot_date' => now()->subDay()->toDateString(),
    ]);

    // Oldest snapshot should be ignored
    AgencyRiskSnapshot::factory()->create([
        'agency_id' => $this->agency->id,
        'risk_score' => 50,
        'snapshot_date' => now()->subDays(2)->toDateString(),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonFragment(['risk_delta' => 20]); // 200 - 180
});

it('does not leak risk snapshot data from another agency', function (): void {
    $otherAgency = Agency::factory()->create();

    AgencyRiskSnapshot::factory()->create([
        'agency_id' => $otherAgency->id,
        'risk_score' => 999,
        'snapshot_date' => now()->toDateString(),
    ]);

    AgencyRiskSnapshot::factory()->create([
        'agency_id' => $otherAgency->id,
        'risk_score' => 888,
        'snapshot_date' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonFragment(['risk_delta' => null]);
});

// ── Organizations count ───────────────────────────────────────────────────────

it('reports the correct count of organizations for the agency', function (): void {
    Organization::factory()->count(2)->create(['agency_id' => $this->agency->id]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.governance-report', $this->agency->id))
        ->assertOk()
        ->assertJsonFragment(['organizations_count' => 3]); // 1 from beforeEach + 2 new
});
