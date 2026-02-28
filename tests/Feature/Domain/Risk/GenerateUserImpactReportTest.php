<?php

use App\Domain\Risk\GenerateUserImpactReport;
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

    $this->service = app(GenerateUserImpactReport::class);
});

it('returns the correct top-level structure', function (): void {
    $report = $this->service->handle($this->organization);

    expect($report)->toHaveKeys([
        'organization_id',
        'total_open_issues',
        'estimated_user_impact_score',
        'impact_distribution',
        'affected_wcag_categories',
        'assistive_technology_risk',
        'generated_at',
    ]);
});

it('returns zero defaults when there are no issues', function (): void {
    $report = $this->service->handle($this->organization);

    expect($report['organization_id'])->toBe($this->organization->id)
        ->and($report['total_open_issues'])->toBe(0)
        ->and($report['estimated_user_impact_score'])->toBe(0)
        ->and($report['impact_distribution'])->toBe([
            'high_impact' => 0,
            'moderate_impact' => 0,
            'low_impact' => 0,
        ])
        ->and($report['affected_wcag_categories'])->toBe([
            'perceivable' => 0,
            'operable' => 0,
            'understandable' => 0,
            'robust' => 0,
        ])
        ->and($report['assistive_technology_risk'])->toBe([
            'screen_reader' => 0,
            'keyboard_navigation' => 0,
            'low_vision' => 0,
        ]);
});

it('counts total_open_issues correctly', function (): void {
    Issue::factory(5)->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $report = $this->service->handle($this->organization);

    expect($report['total_open_issues'])->toBe(5);
});

it('excludes resolved issues from all counts', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->resolved()->create([
        'severity' => IssueSeverity::Critical,
        'rule_key' => 'wcag-1.1.1',
        'first_detected_at' => now()->subDays(10),
        'last_detected_at' => now()->subDays(5),
    ]);

    $report = $this->service->handle($this->organization);

    expect($report['total_open_issues'])->toBe(0)
        ->and($report['estimated_user_impact_score'])->toBe(0)
        ->and($report['impact_distribution']['high_impact'])->toBe(0)
        ->and($report['affected_wcag_categories']['perceivable'])->toBe(0)
        ->and($report['assistive_technology_risk']['screen_reader'])->toBe(0);
});

it('maps Critical and High severity to high_impact', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Critical,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::High,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $report = $this->service->handle($this->organization);

    expect($report['impact_distribution']['high_impact'])->toBe(2)
        ->and($report['impact_distribution']['moderate_impact'])->toBe(0)
        ->and($report['impact_distribution']['low_impact'])->toBe(0);
});

it('maps Medium severity to moderate_impact', function (): void {
    Issue::factory(3)->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Medium,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $report = $this->service->handle($this->organization);

    expect($report['impact_distribution']['moderate_impact'])->toBe(3)
        ->and($report['impact_distribution']['high_impact'])->toBe(0)
        ->and($report['impact_distribution']['low_impact'])->toBe(0);
});

it('maps Low severity to low_impact', function (): void {
    Issue::factory(4)->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Low,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $report = $this->service->handle($this->organization);

    expect($report['impact_distribution']['low_impact'])->toBe(4)
        ->and($report['impact_distribution']['high_impact'])->toBe(0)
        ->and($report['impact_distribution']['moderate_impact'])->toBe(0);
});

it('returns 100 impact score when all issues are high impact', function (): void {
    Issue::factory(5)->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Critical,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $report = $this->service->handle($this->organization);

    expect($report['estimated_user_impact_score'])->toBe(100);
});

it('returns 33 impact score when all issues are low impact', function (): void {
    Issue::factory(5)->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Low,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $report = $this->service->handle($this->organization);

    expect($report['estimated_user_impact_score'])->toBe(33);
});

it('classifies perceivable issues by wcag-1.x.x rule keys', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'rule_key' => 'wcag-1.4.3',
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'rule_key' => 'wcag-2.1.1',
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $report = $this->service->handle($this->organization);

    expect($report['affected_wcag_categories']['perceivable'])->toBe(1)
        ->and($report['affected_wcag_categories']['operable'])->toBe(1)
        ->and($report['affected_wcag_categories']['understandable'])->toBe(0)
        ->and($report['affected_wcag_categories']['robust'])->toBe(0);
});

it('classifies all four wcag categories from rule keys', function (): void {
    foreach (['wcag-1.1.1', 'wcag-2.4.4', 'wcag-3.1.1', 'wcag-4.1.2'] as $ruleKey) {
        Issue::factory()->for($this->agency)->for($this->organization)->create([
            'status' => IssueStatus::Open,
            'rule_key' => $ruleKey,
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);
    }

    $report = $this->service->handle($this->organization);

    expect($report['affected_wcag_categories'])->toBe([
        'perceivable' => 1,
        'operable' => 1,
        'understandable' => 1,
        'robust' => 1,
    ]);
});

it('classifies screen_reader risk from wcag-1.1.x, wcag-1.3.x, and wcag-4.1.x rules', function (): void {
    foreach (['wcag-1.1.1', 'wcag-1.3.1', 'wcag-4.1.2'] as $ruleKey) {
        Issue::factory()->for($this->agency)->for($this->organization)->create([
            'status' => IssueStatus::Open,
            'rule_key' => $ruleKey,
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);
    }

    $report = $this->service->handle($this->organization);

    expect($report['assistive_technology_risk']['screen_reader'])->toBe(3);
});

it('classifies keyboard_navigation risk from wcag-2.1.x and wcag-2.4.x rules', function (): void {
    foreach (['wcag-2.1.1', 'wcag-2.4.4', 'wcag-2.4.7'] as $ruleKey) {
        Issue::factory()->for($this->agency)->for($this->organization)->create([
            'status' => IssueStatus::Open,
            'rule_key' => $ruleKey,
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);
    }

    $report = $this->service->handle($this->organization);

    expect($report['assistive_technology_risk']['keyboard_navigation'])->toBe(3);
});

it('classifies low_vision risk from wcag-1.4.x rules', function (): void {
    foreach (['wcag-1.4.3', 'wcag-1.4.4'] as $ruleKey) {
        Issue::factory()->for($this->agency)->for($this->organization)->create([
            'status' => IssueStatus::Open,
            'rule_key' => $ruleKey,
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);
    }

    $report = $this->service->handle($this->organization);

    expect($report['assistive_technology_risk']['low_vision'])->toBe(2);
});

it('does not include data from other organizations', function (): void {
    $otherOrganization = Organization::factory()->create(['agency_id' => $this->agency->id]);

    Issue::factory(5)->for($this->agency)->for($otherOrganization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Critical,
        'rule_key' => 'wcag-1.1.1',
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $report = $this->service->handle($this->organization);

    expect($report['total_open_issues'])->toBe(0)
        ->and($report['estimated_user_impact_score'])->toBe(0)
        ->and($report['impact_distribution']['high_impact'])->toBe(0)
        ->and($report['affected_wcag_categories']['perceivable'])->toBe(0)
        ->and($report['assistive_technology_risk']['screen_reader'])->toBe(0);
});

it('includes a generated_at ISO 8601 timestamp', function (): void {
    $report = $this->service->handle($this->organization);

    expect($report['generated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});
