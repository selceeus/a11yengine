<?php

use App\Domain\Risk\GenerateGovernanceSummary;
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
    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
});

it('returns 200 with the correct response structure', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.organizations.governance-summary', $this->organization->id))
        ->assertOk()
        ->assertJsonStructure([
            'organization_id',
            'organization_name',
            'total_risk_score',
            'risk_delta',
            'open_issues',
            'severity_distribution' => [
                'critical' => ['count', 'risk_contribution'],
                'serious' => ['count', 'risk_contribution'],
                'moderate' => ['count', 'risk_contribution'],
                'minor' => ['count', 'risk_contribution'],
            ],
            'aging_distribution' => ['under_30_days', '30_to_60_days', 'over_60_days'],
            'estimated_user_impact_score',
            'impact_distribution' => ['high_impact', 'moderate_impact', 'low_impact'],
            'affected_wcag_categories' => ['perceivable', 'operable', 'understandable', 'robust'],
            'assistive_technology_risk' => ['screen_reader', 'keyboard_navigation', 'low_vision'],
            'generated_at',
        ]);
});

it('returns the correct organization identifiers', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.organizations.governance-summary', $this->organization->id))
        ->assertOk()
        ->assertJsonFragment([
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
        ]);
});

it('returns 404 when the organization does not exist', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.organizations.governance-summary', 99999))
        ->assertNotFound();
});

it('calls GenerateGovernanceSummary with the correct organization', function (): void {
    $mock = $this->mock(GenerateGovernanceSummary::class);

    $mock->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Organization $org): bool => $org->id === $this->organization->id)
        ->andReturn([
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'total_risk_score' => 0,
            'risk_delta' => null,
            'open_issues' => 0,
            'severity_distribution' => [
                'critical' => ['count' => 0, 'risk_contribution' => 0],
                'serious' => ['count' => 0, 'risk_contribution' => 0],
                'moderate' => ['count' => 0, 'risk_contribution' => 0],
                'minor' => ['count' => 0, 'risk_contribution' => 0],
            ],
            'aging_distribution' => ['under_30_days' => 0, '30_to_60_days' => 0, 'over_60_days' => 0],
            'estimated_user_impact_score' => 0,
            'impact_distribution' => ['high_impact' => 0, 'moderate_impact' => 0, 'low_impact' => 0],
            'affected_wcag_categories' => ['perceivable' => 0, 'operable' => 0, 'understandable' => 0, 'robust' => 0],
            'assistive_technology_risk' => ['screen_reader' => 0, 'keyboard_navigation' => 0, 'low_vision' => 0],
            'generated_at' => now()->toIso8601String(),
        ]);

    $this->actingAs($this->user)
        ->getJson(route('api.organizations.governance-summary', $this->organization->id))
        ->assertOk();
});

it('returns combined breakdown and impact data', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Critical,
        'rule_key' => 'wcag-1.1.1',
        'risk_weight' => 50,
        'occurrence_count' => 2,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.organizations.governance-summary', $this->organization->id))
        ->assertOk()
        ->assertJsonFragment(['total_risk_score' => 100, 'open_issues' => 1])
        ->assertJsonPath('severity_distribution.critical.count', 1)
        ->assertJsonPath('impact_distribution.high_impact', 1)
        ->assertJsonPath('affected_wcag_categories.perceivable', 1)
        ->assertJsonPath('assistive_technology_risk.screen_reader', 1);
});
