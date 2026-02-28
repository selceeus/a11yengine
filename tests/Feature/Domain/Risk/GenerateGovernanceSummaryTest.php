<?php

use App\Domain\Risk\GenerateGovernanceSummary;
use App\Domain\Risk\GenerateRiskBreakdown;
use App\Domain\Risk\GenerateUserImpactReport;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\RiskSnapshot;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->service = app(GenerateGovernanceSummary::class);
});

it('returns the correct top-level structure', function (): void {
    $summary = $this->service->handle($this->organization);

    expect($summary)->toHaveKeys([
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
    ]);
});

it('returns zero defaults when there are no issues or snapshots', function (): void {
    $summary = $this->service->handle($this->organization);

    expect($summary['organization_id'])->toBe($this->organization->id)
        ->and($summary['organization_name'])->toBe($this->organization->name)
        ->and($summary['total_risk_score'])->toBe(0)
        ->and($summary['risk_delta'])->toBeNull()
        ->and($summary['open_issues'])->toBe(0)
        ->and($summary['severity_distribution'])->toBe([
            'critical' => ['count' => 0, 'risk_contribution' => 0],
            'serious' => ['count' => 0, 'risk_contribution' => 0],
            'moderate' => ['count' => 0, 'risk_contribution' => 0],
            'minor' => ['count' => 0, 'risk_contribution' => 0],
        ])
        ->and($summary['aging_distribution'])->toBe([
            'under_30_days' => 0,
            '30_to_60_days' => 0,
            'over_60_days' => 0,
        ])
        ->and($summary['estimated_user_impact_score'])->toBe(0)
        ->and($summary['impact_distribution'])->toBe([
            'high_impact' => 0,
            'moderate_impact' => 0,
            'low_impact' => 0,
        ])
        ->and($summary['affected_wcag_categories'])->toBe([
            'perceivable' => 0,
            'operable' => 0,
            'understandable' => 0,
            'robust' => 0,
        ])
        ->and($summary['assistive_technology_risk'])->toBe([
            'screen_reader' => 0,
            'keyboard_navigation' => 0,
            'low_vision' => 0,
        ]);
});

it('returns risk_delta as null with fewer than two snapshots', function (): void {
    RiskSnapshot::query()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'total_risk_score' => 500,
        'open_issue_count' => 10,
        'snapshot_date' => today(),
        'created_at' => now(),
    ]);

    $summary = $this->service->handle($this->organization);

    expect($summary['risk_delta'])->toBeNull();
});

it('calculates risk_delta from the two most recent snapshots', function (): void {
    RiskSnapshot::query()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'total_risk_score' => 800,
        'open_issue_count' => 20,
        'snapshot_date' => today()->subDay(),
        'created_at' => now()->subDay(),
    ]);

    RiskSnapshot::query()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'total_risk_score' => 600,
        'open_issue_count' => 15,
        'snapshot_date' => today(),
        'created_at' => now(),
    ]);

    $summary = $this->service->handle($this->organization);

    // most recent (600) - previous (800) = -200
    expect($summary['risk_delta'])->toBe(-200);
});

it('delegates breakdown data to GenerateRiskBreakdown', function (): void {
    $mockBreakdown = $this->mock(GenerateRiskBreakdown::class);

    $mockBreakdown->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Organization $org): bool => $org->id === $this->organization->id)
        ->andReturn([
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'total_risk_score' => 99,
            'open_issues' => 5,
            'severity_distribution' => [
                'critical' => ['count' => 1, 'risk_contribution' => 99],
                'serious' => ['count' => 0, 'risk_contribution' => 0],
                'moderate' => ['count' => 0, 'risk_contribution' => 0],
                'minor' => ['count' => 0, 'risk_contribution' => 0],
            ],
            'aging_distribution' => ['under_30_days' => 5, '30_to_60_days' => 0, 'over_60_days' => 0],
            'highest_risk_rules' => [],
            'generated_at' => now()->toIso8601String(),
        ]);

    app(GenerateGovernanceSummary::class)->handle($this->organization);
});

it('delegates impact data to GenerateUserImpactReport', function (): void {
    $mockImpact = $this->mock(GenerateUserImpactReport::class);

    $mockImpact->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Organization $org): bool => $org->id === $this->organization->id)
        ->andReturn([
            'organization_id' => $this->organization->id,
            'total_open_issues' => 5,
            'estimated_user_impact_score' => 75,
            'impact_distribution' => ['high_impact' => 5, 'moderate_impact' => 0, 'low_impact' => 0],
            'affected_wcag_categories' => ['perceivable' => 1, 'operable' => 4, 'understandable' => 0, 'robust' => 0],
            'assistive_technology_risk' => ['screen_reader' => 1, 'keyboard_navigation' => 4, 'low_vision' => 0],
            'generated_at' => now()->toIso8601String(),
        ]);

    app(GenerateGovernanceSummary::class)->handle($this->organization);
});

it('merges breakdown and impact data into a single response', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Critical,
        'rule_key' => 'wcag-2.1.1',
        'risk_weight' => 80,
        'occurrence_count' => 1,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $summary = $this->service->handle($this->organization);

    expect($summary['total_risk_score'])->toBe(80)
        ->and($summary['open_issues'])->toBe(1)
        ->and($summary['severity_distribution']['critical']['count'])->toBe(1)
        ->and($summary['estimated_user_impact_score'])->toBe(100)
        ->and($summary['impact_distribution']['high_impact'])->toBe(1)
        ->and($summary['affected_wcag_categories']['operable'])->toBe(1)
        ->and($summary['assistive_technology_risk']['keyboard_navigation'])->toBe(1);
});

it('does not include highest_risk_rules in the response', function (): void {
    $summary = $this->service->handle($this->organization);

    expect($summary)->not->toHaveKey('highest_risk_rules');
});

it('does not include total_open_issues in the response', function (): void {
    $summary = $this->service->handle($this->organization);

    expect($summary)->not->toHaveKey('total_open_issues');
});

it('includes a generated_at ISO 8601 timestamp', function (): void {
    $summary = $this->service->handle($this->organization);

    expect($summary['generated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});
