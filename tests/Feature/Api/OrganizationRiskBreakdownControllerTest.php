<?php

use App\Domain\Risk\GenerateRiskBreakdown;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->user = User::factory()
        ->withRole(UserRole::AgencyAdmin, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);
});

it('returns 200 with the correct response structure', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.organizations.risk-breakdown', $this->organization->id))
        ->assertOk()
        ->assertJsonStructure([
            'organization_id',
            'organization_name',
            'total_risk_score',
            'open_issues',
            'severity_distribution' => [
                'critical' => ['count', 'risk_contribution'],
                'serious' => ['count', 'risk_contribution'],
                'moderate' => ['count', 'risk_contribution'],
                'minor' => ['count', 'risk_contribution'],
            ],
            'aging_distribution' => [
                'under_30_days',
                '30_to_60_days',
                'over_60_days',
            ],
            'highest_risk_rules',
            'generated_at',
        ]);
});

it('returns the correct organization identifiers', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.organizations.risk-breakdown', $this->organization->id))
        ->assertOk()
        ->assertJsonFragment([
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
        ]);
});

it('returns 404 when the organization does not exist', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.organizations.risk-breakdown', 99999))
        ->assertNotFound();
});

it('calls GenerateRiskBreakdown with the correct organization', function (): void {
    $mock = $this->mock(GenerateRiskBreakdown::class);

    $mock->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Organization $org): bool => $org->id === $this->organization->id)
        ->andReturn([
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'total_risk_score' => 0,
            'open_issues' => 0,
            'severity_distribution' => [
                'critical' => ['count' => 0, 'risk_contribution' => 0],
                'serious' => ['count' => 0, 'risk_contribution' => 0],
                'moderate' => ['count' => 0, 'risk_contribution' => 0],
                'minor' => ['count' => 0, 'risk_contribution' => 0],
            ],
            'aging_distribution' => [
                'under_30_days' => 0,
                '30_to_60_days' => 0,
                'over_60_days' => 0,
            ],
            'highest_risk_rules' => [],
            'generated_at' => now()->toIso8601String(),
        ]);

    $this->actingAs($this->user)
        ->getJson(route('api.organizations.risk-breakdown', $this->organization->id))
        ->assertOk();
});

it('returns aggregated risk data for the organizations open issues', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => \App\Enums\IssueStatus::Open,
        'rule_key' => 'wcag-1.1.1',
        'risk_weight' => 50,
        'occurrence_count' => 2,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.organizations.risk-breakdown', $this->organization->id))
        ->assertOk()
        ->assertJsonFragment(['total_risk_score' => 100, 'open_issues' => 1])
        ->assertJsonPath('highest_risk_rules.0.rule_key', 'wcag-1.1.1')
        ->assertJsonPath('highest_risk_rules.0.risk_contribution', 100);
});

it('returns 401 for unauthenticated requests', function (): void {
    $this->getJson(route('api.organizations.risk-breakdown', $this->organization->id))
        ->assertUnauthorized();
});
