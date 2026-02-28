<?php

use App\Domain\Risk\GenerateUserImpactReport;
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
        ->getJson(route('api.organizations.user-impact', $this->organization->id))
        ->assertOk()
        ->assertJsonStructure([
            'organization_id',
            'total_open_issues',
            'estimated_user_impact_score',
            'impact_distribution' => ['high_impact', 'moderate_impact', 'low_impact'],
            'affected_wcag_categories' => ['perceivable', 'operable', 'understandable', 'robust'],
            'assistive_technology_risk' => ['screen_reader', 'keyboard_navigation', 'low_vision'],
            'generated_at',
        ]);
});

it('returns the correct organization_id', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.organizations.user-impact', $this->organization->id))
        ->assertOk()
        ->assertJsonFragment(['organization_id' => $this->organization->id]);
});

it('returns 404 when the organization does not exist', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.organizations.user-impact', 99999))
        ->assertNotFound();
});

it('calls GenerateUserImpactReport with the correct organization', function (): void {
    $mock = $this->mock(GenerateUserImpactReport::class);

    $mock->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Organization $org): bool => $org->id === $this->organization->id)
        ->andReturn([
            'organization_id' => $this->organization->id,
            'total_open_issues' => 0,
            'estimated_user_impact_score' => 0,
            'impact_distribution' => ['high_impact' => 0, 'moderate_impact' => 0, 'low_impact' => 0],
            'affected_wcag_categories' => ['perceivable' => 0, 'operable' => 0, 'understandable' => 0, 'robust' => 0],
            'assistive_technology_risk' => ['screen_reader' => 0, 'keyboard_navigation' => 0, 'low_vision' => 0],
            'generated_at' => now()->toIso8601String(),
        ]);

    $this->actingAs($this->user)
        ->getJson(route('api.organizations.user-impact', $this->organization->id))
        ->assertOk();
});

it('returns aggregated impact data for the organizations open issues', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => \App\Enums\IssueSeverity::Critical,
        'rule_key' => 'wcag-2.1.1',
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.organizations.user-impact', $this->organization->id))
        ->assertOk()
        ->assertJsonFragment(['total_open_issues' => 1, 'estimated_user_impact_score' => 100])
        ->assertJsonPath('impact_distribution.high_impact', 1)
        ->assertJsonPath('affected_wcag_categories.operable', 1)
        ->assertJsonPath('assistive_technology_risk.keyboard_navigation', 1);
});
