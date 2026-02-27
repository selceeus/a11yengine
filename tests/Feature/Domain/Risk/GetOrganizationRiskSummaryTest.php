<?php

use App\Domain\Risk\GetOrganizationRiskSummary;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->service = app(GetOrganizationRiskSummary::class);
});

it('returns the correct structure with zero values when there are no issues', function (): void {
    $summary = $this->service->handle($this->organization);

    expect($summary)
        ->toHaveKeys(['total_risk_score', 'open_issues', 'by_severity', 'aging_buckets'])
        ->and($summary['total_risk_score'])->toBe(0)
        ->and($summary['open_issues'])->toBe(0)
        ->and($summary['by_severity'])->toBe([
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'critical' => 0,
        ])
        ->and($summary['aging_buckets'])->toBe([
            'under_30_days' => 0,
            '30_to_60_days' => 0,
            'over_60_days' => 0,
        ]);
});

it('calculates total_risk_score correctly', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 10,
        'occurrence_count' => 3,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $summary = $this->service->handle($this->organization);

    expect($summary['total_risk_score'])->toBe(30)
        ->and($summary['open_issues'])->toBe(1);
});

it('groups open issues by severity', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Critical,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    Issue::factory(2)->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::High,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $summary = $this->service->handle($this->organization);

    expect($summary['by_severity']['critical'])->toBe(1)
        ->and($summary['by_severity']['high'])->toBe(2)
        ->and($summary['by_severity']['medium'])->toBe(0)
        ->and($summary['by_severity']['low'])->toBe(0);
});

it('excludes resolved issues from all counts', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Resolved,
        'severity' => IssueSeverity::Critical,
        'risk_weight' => 100,
        'occurrence_count' => 5,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $summary = $this->service->handle($this->organization);

    expect($summary['total_risk_score'])->toBe(0)
        ->and($summary['open_issues'])->toBe(0)
        ->and($summary['by_severity']['critical'])->toBe(0);
});

it('buckets issues correctly by age', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'first_detected_at' => now()->subDays(10),
        'last_detected_at' => now(),
    ]);

    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'first_detected_at' => now()->subDays(45),
        'last_detected_at' => now(),
    ]);

    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'first_detected_at' => now()->subDays(90),
        'last_detected_at' => now(),
    ]);

    $summary = $this->service->handle($this->organization);

    expect($summary['aging_buckets'])->toBe([
        'under_30_days' => 1,
        '30_to_60_days' => 1,
        'over_60_days' => 1,
    ]);
});

it('returns correct data via the API endpoint', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 20,
        'occurrence_count' => 2,
        'severity' => IssueSeverity::Critical,
        'first_detected_at' => now()->subDays(5),
        'last_detected_at' => now(),
    ]);

    $this->withoutMiddleware()
        ->getJson("/api/organizations/{$this->organization->id}/risk-summary")
        ->assertOk()
        ->assertJsonStructure([
            'total_risk_score',
            'open_issues',
            'by_severity' => ['low', 'medium', 'high', 'critical'],
            'aging_buckets' => ['under_30_days', '30_to_60_days', 'over_60_days'],
        ])
        ->assertJsonFragment([
            'total_risk_score' => 40,
            'open_issues' => 1,
        ]);
});
