<?php

use App\Domain\Risk\GetOrganizationGovernanceSummary;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\RiskSnapshot;
use App\Models\Scan;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);

    $user = User::factory()
        ->withRole(UserRole::AgencyAdmin, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->service = app(GetOrganizationGovernanceSummary::class);
});

it('returns the correct response structure', function (): void {
    $summary = $this->service->handle($this->organization);

    expect($summary)->toHaveKeys([
        'organization_id',
        'risk_score',
        'risk_delta',
        'open_issues',
        'new_issues_since_last_scan',
        'resolved_issues_since_last_scan',
        'aging_high_risk_issues',
        'last_scan_at',
        'snapshot_at',
    ]);
});

it('returns zero defaults when there is no data', function (): void {
    $summary = $this->service->handle($this->organization);

    expect($summary['risk_score'])->toBe(0)
        ->and($summary['risk_delta'])->toBeNull()
        ->and($summary['open_issues'])->toBe(0)
        ->and($summary['new_issues_since_last_scan'])->toBe(0)
        ->and($summary['resolved_issues_since_last_scan'])->toBe(0)
        ->and($summary['aging_high_risk_issues'])->toBe(0)
        ->and($summary['last_scan_at'])->toBeNull();
});

it('calculates the correct risk_score', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 10,
        'occurrence_count' => 5,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    expect($this->service->handle($this->organization)['risk_score'])->toBe(50);
});

it('calculates risk_delta as null when fewer than two snapshots exist', function (): void {
    RiskSnapshot::query()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'total_risk_score' => 100,
        'open_issue_count' => 5,
        'snapshot_date' => today(),
        'created_at' => now(),
    ]);

    expect($this->service->handle($this->organization)['risk_delta'])->toBeNull();
});

it('calculates risk_delta correctly from the two most recent snapshots', function (): void {
    RiskSnapshot::query()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'total_risk_score' => 200,
        'open_issue_count' => 10,
        'snapshot_date' => today()->subDay(),
        'created_at' => now()->subDay(),
    ]);

    RiskSnapshot::query()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'total_risk_score' => 300,
        'open_issue_count' => 12,
        'snapshot_date' => today(),
        'created_at' => now(),
    ]);

    // latest (300) - previous (200) = +100
    expect($this->service->handle($this->organization)['risk_delta'])->toBe(100);
});

it('counts new issues since the last completed scan', function (): void {
    $scan = Scan::factory()->for($this->agency)->for($this->organization)->create([
        'status' => 'completed',
        'completed_at' => now()->subHour(),
    ]);

    // Created after scan — new
    Issue::factory(2)->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    // Created before scan — not new
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'first_detected_at' => now()->subDays(2),
        'last_detected_at' => now()->subDays(2),
    ]);

    expect($this->service->handle($this->organization)['new_issues_since_last_scan'])->toBe(2);
});

it('counts resolved issues since the last completed scan', function (): void {
    Scan::factory()->for($this->agency)->for($this->organization)->create([
        'status' => 'completed',
        'completed_at' => now()->subHour(),
    ]);

    // Resolved after scan — counted
    Issue::factory()->for($this->agency)->for($this->organization)->resolved()->create([
        'first_detected_at' => now()->subDays(5),
        'last_detected_at' => now()->subHours(2),
        'resolved_at' => now()->subMinutes(30),
    ]);

    // Resolved before scan — not counted
    Issue::factory()->for($this->agency)->for($this->organization)->resolved()->create([
        'first_detected_at' => now()->subDays(10),
        'last_detected_at' => now()->subDays(5),
        'resolved_at' => now()->subDays(2),
    ]);

    expect($this->service->handle($this->organization)['resolved_issues_since_last_scan'])->toBe(1);
});

it('counts aging high-risk open issues older than 30 days', function (): void {
    // High severity, 40 days old — should be counted
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::High,
        'first_detected_at' => now()->subDays(40),
        'last_detected_at' => now(),
    ]);

    // Critical severity, 60 days old — should be counted
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Critical,
        'first_detected_at' => now()->subDays(60),
        'last_detected_at' => now(),
    ]);

    // High severity but only 10 days old — should NOT be counted
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::High,
        'first_detected_at' => now()->subDays(10),
        'last_detected_at' => now(),
    ]);

    // Medium severity, 40 days old — should NOT be counted
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Medium,
        'first_detected_at' => now()->subDays(40),
        'last_detected_at' => now(),
    ]);

    expect($this->service->handle($this->organization)['aging_high_risk_issues'])->toBe(2);
});

it('returns the endpoint response correctly via the API', function (): void {
    $this->withoutMiddleware()
        ->getJson("/api/organizations/{$this->organization->id}/governance-summary")
        ->assertOk()
        ->assertJsonStructure([
            'organization_id',
            'organization_name',
            'total_risk_score',
            'risk_delta',
            'open_issues',
            'severity_distribution',
            'aging_distribution',
            'estimated_user_impact_score',
            'impact_distribution',
            'affected_wcag_categories',
            'assistive_technology_risk',
            'generated_at',
        ])
        ->assertJsonFragment(['organization_id' => $this->organization->id]);
});
