<?php

use App\Domain\Risk\GetOrganizationRiskSummary;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\RiskSnapshot;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->agencyAdmin = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->agencyAdmin->roles()->create([
        'role' => UserRole::AgencyAdmin->value,
        'agency_id' => $this->agency->id,
    ]);
});

// ── Authentication ────────────────────────────────────────────────────────────

it('returns 401 for unauthenticated requests', function (): void {
    $this->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertUnauthorized();
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('allows a super user to access any organization', function (): void {
    $superUser = User::factory()->create(['agency_id' => null]);
    $superUser->roles()->create(['role' => UserRole::SuperUser->value]);

    $this->actingAs($superUser)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertOk();
});

it('allows an agency admin to access organizations within their agency', function (): void {
    $this->actingAs($this->agencyAdmin)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertOk();
});

it('allows an org admin to access their own organization', function (): void {
    $orgAdmin = User::factory()->create(['agency_id' => $this->agency->id]);
    $orgAdmin->roles()->create([
        'role' => UserRole::OrgAdmin->value,
        'organization_id' => $this->organization->id,
    ]);

    $this->actingAs($orgAdmin)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertOk();
});

it('returns 403 for an org admin trying to access a different organization', function (): void {
    $otherOrg = Organization::factory()->create(['agency_id' => $this->agency->id]);

    $orgAdmin = User::factory()->create(['agency_id' => $this->agency->id]);
    $orgAdmin->roles()->create([
        'role' => UserRole::OrgAdmin->value,
        'organization_id' => $this->organization->id,
    ]);

    $this->actingAs($orgAdmin)
        ->getJson(route('api.organizations.risk-summary', $otherOrg->id))
        ->assertForbidden();
});

it('returns 403 for a viewer role', function (): void {
    $viewer = User::factory()->create(['agency_id' => $this->agency->id]);
    $viewer->roles()->create([
        'role' => UserRole::Viewer->value,
        'agency_id' => $this->agency->id,
    ]);

    $this->actingAs($viewer)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertForbidden();
});

it('returns 403 for an editor role', function (): void {
    $editor = User::factory()->create(['agency_id' => $this->agency->id]);
    $editor->roles()->create([
        'role' => UserRole::Editor->value,
        'agency_id' => $this->agency->id,
    ]);

    $this->actingAs($editor)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertForbidden();
});

it('returns 403 for a prop admin role', function (): void {
    $propAdmin = User::factory()->create(['agency_id' => $this->agency->id]);
    $propAdmin->roles()->create([
        'role' => UserRole::PropAdmin->value,
        'agency_id' => $this->agency->id,
    ]);

    $this->actingAs($propAdmin)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertForbidden();
});

it('returns 403 when an agency admin from a different agency tries to access the organization', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherAdmin = User::factory()->create(['agency_id' => $otherAgency->id]);
    $otherAdmin->roles()->create([
        'role' => UserRole::AgencyAdmin->value,
        'agency_id' => $otherAgency->id,
    ]);

    $this->actingAs($otherAdmin)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertForbidden();
});

// ── Response structure ────────────────────────────────────────────────────────

it('returns the correct top-level JSON structure', function (): void {
    $this->actingAs($this->agencyAdmin)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertOk()
        ->assertJsonStructure([
            'total_risk_score',
            'open_issues',
            'open_issue_count',
            'by_severity' => ['low', 'medium', 'high', 'critical'],
            'aging_buckets' => ['under_30_days', '30_to_60_days', 'over_60_days'],
            'avg_days_to_resolution',
            'resolved_last_30_days',
            'net_issue_delta_per_scan',
            'snapshots',
        ]);
});

it('open_issue_count mirrors open_issues in the response', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create(['status' => IssueStatus::Open]);

    $response = $this->actingAs($this->agencyAdmin)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertOk();

    expect($response->json('open_issue_count'))->toBe($response->json('open_issues'));
});

it('returns 404 when the organization does not exist', function (): void {
    $this->actingAs($this->agencyAdmin)
        ->getJson(route('api.organizations.risk-summary', 99999))
        ->assertNotFound();
});

// ── Snapshots ─────────────────────────────────────────────────────────────────

it('returns an empty snapshots array when no risk snapshots exist', function (): void {
    $this->actingAs($this->agencyAdmin)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertOk()
        ->assertJson(['snapshots' => []]);
});

it('returns snapshots with the expected shape', function (): void {
    RiskSnapshot::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'total_risk_score' => 120,
        'open_issue_count' => 7,
        'snapshot_date' => now()->subDay()->toDateString(),
    ]);

    $response = $this->actingAs($this->agencyAdmin)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertOk();

    $snapshot = $response->json('snapshots.0');

    expect($snapshot)->toHaveKeys(['snapshot_date', 'total_risk_score', 'open_issue_count'])
        ->and($snapshot['total_risk_score'])->toBe(120)
        ->and($snapshot['open_issue_count'])->toBe(7);
});

it('orders snapshots by date descending', function (): void {
    RiskSnapshot::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'snapshot_date' => now()->subDays(3)->toDateString(),
        'total_risk_score' => 50,
        'open_issue_count' => 2,
    ]);

    RiskSnapshot::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'snapshot_date' => now()->subDay()->toDateString(),
        'total_risk_score' => 100,
        'open_issue_count' => 5,
    ]);

    $response = $this->actingAs($this->agencyAdmin)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertOk();

    $dates = collect($response->json('snapshots'))->pluck('snapshot_date')->toArray();

    expect($dates[0])->toBeGreaterThan($dates[1]);
});

it('does not include snapshots from a different organization', function (): void {
    $otherOrg = Organization::factory()->create(['agency_id' => $this->agency->id]);

    RiskSnapshot::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $otherOrg->id,
        'snapshot_date' => now()->toDateString(),
        'total_risk_score' => 999,
        'open_issue_count' => 50,
    ]);

    $response = $this->actingAs($this->agencyAdmin)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertOk();

    expect($response->json('snapshots'))->toBeEmpty();
});

it('returns at most 30 snapshots', function (): void {
    foreach (range(1, 35) as $i) {
        RiskSnapshot::factory()->create([
            'agency_id' => $this->agency->id,
            'organization_id' => $this->organization->id,
            'snapshot_date' => now()->subDays($i)->toDateString(),
            'total_risk_score' => $i,
            'open_issue_count' => 1,
        ]);
    }

    $response = $this->actingAs($this->agencyAdmin)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertOk();

    expect(count($response->json('snapshots')))->toBeLessThanOrEqual(30);
});

// ── Aggregated risk data ───────────────────────────────────────────────────────

it('returns zero values when the organization has no issues', function (): void {
    $this->actingAs($this->agencyAdmin)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertOk()
        ->assertJsonFragment([
            'total_risk_score' => 0,
            'open_issues' => 0,
            'open_issue_count' => 0,
        ]);
});

it('reflects open issue counts correctly', function (): void {
    Issue::factory()->count(3)->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::High,
    ]);

    $response = $this->actingAs($this->agencyAdmin)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertOk();

    expect($response->json('open_issues'))->toBe(3)
        ->and($response->json('open_issue_count'))->toBe(3)
        ->and($response->json('by_severity.high'))->toBe(3);
});

it('delegates data retrieval to the GetOrganizationRiskSummary service', function (): void {
    $mock = $this->mock(GetOrganizationRiskSummary::class);

    $mock->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Organization $org): bool => $org->id === $this->organization->id)
        ->andReturn([
            'total_risk_score' => 42,
            'open_issues' => 5,
            'by_severity' => ['low' => 1, 'medium' => 2, 'high' => 1, 'critical' => 1],
            'aging_buckets' => ['under_30_days' => 3, '30_to_60_days' => 1, 'over_60_days' => 1],
            'avg_days_to_resolution' => null,
            'resolved_last_30_days' => 0,
            'net_issue_delta_per_scan' => 0.0,
        ]);

    $this->actingAs($this->agencyAdmin)
        ->getJson(route('api.organizations.risk-summary', $this->organization->id))
        ->assertOk()
        ->assertJsonFragment(['total_risk_score' => 42, 'open_issues' => 5, 'open_issue_count' => 5]);
});
