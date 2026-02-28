<?php

use App\Domain\Risk\GenerateRiskBreakdown;
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

    $this->service = app(GenerateRiskBreakdown::class);
});

it('returns the correct top-level structure', function (): void {
    $breakdown = $this->service->handle($this->organization);

    expect($breakdown)->toHaveKeys([
        'organization_id',
        'organization_name',
        'total_risk_score',
        'open_issues',
        'severity_distribution',
        'aging_distribution',
        'highest_risk_rules',
        'generated_at',
    ]);
});

it('returns zero defaults when there are no issues', function (): void {
    $breakdown = $this->service->handle($this->organization);

    expect($breakdown['organization_id'])->toBe($this->organization->id)
        ->and($breakdown['organization_name'])->toBe($this->organization->name)
        ->and($breakdown['total_risk_score'])->toBe(0)
        ->and($breakdown['open_issues'])->toBe(0)
        ->and($breakdown['severity_distribution'])->toBe([
            'critical' => ['count' => 0, 'risk_contribution' => 0],
            'serious' => ['count' => 0, 'risk_contribution' => 0],
            'moderate' => ['count' => 0, 'risk_contribution' => 0],
            'minor' => ['count' => 0, 'risk_contribution' => 0],
        ])
        ->and($breakdown['aging_distribution'])->toBe([
            'under_30_days' => 0,
            '30_to_60_days' => 0,
            'over_60_days' => 0,
        ])
        ->and($breakdown['highest_risk_rules'])->toBe([]);
});

it('calculates total_risk_score and open_issues correctly', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 10,
        'occurrence_count' => 5,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $breakdown = $this->service->handle($this->organization);

    expect($breakdown['total_risk_score'])->toBe(50)
        ->and($breakdown['open_issues'])->toBe(1);
});

it('maps IssueSeverity::Critical to the critical severity distribution key', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Critical,
        'risk_weight' => 80,
        'occurrence_count' => 2,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $breakdown = $this->service->handle($this->organization);

    expect($breakdown['severity_distribution']['critical']['count'])->toBe(1)
        ->and($breakdown['severity_distribution']['critical']['risk_contribution'])->toBe(160);
});

it('maps IssueSeverity::High to the serious severity distribution key', function (): void {
    Issue::factory(3)->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::High,
        'risk_weight' => 30,
        'occurrence_count' => 1,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $breakdown = $this->service->handle($this->organization);

    expect($breakdown['severity_distribution']['serious']['count'])->toBe(3)
        ->and($breakdown['severity_distribution']['serious']['risk_contribution'])->toBe(90);
});

it('maps IssueSeverity::Medium to the moderate severity distribution key', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Medium,
        'risk_weight' => 12,
        'occurrence_count' => 3,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $breakdown = $this->service->handle($this->organization);

    expect($breakdown['severity_distribution']['moderate']['count'])->toBe(1)
        ->and($breakdown['severity_distribution']['moderate']['risk_contribution'])->toBe(36);
});

it('maps IssueSeverity::Low to the minor severity distribution key', function (): void {
    Issue::factory(2)->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Low,
        'risk_weight' => 5,
        'occurrence_count' => 2,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $breakdown = $this->service->handle($this->organization);

    expect($breakdown['severity_distribution']['minor']['count'])->toBe(2)
        ->and($breakdown['severity_distribution']['minor']['risk_contribution'])->toBe(20);
});

it('excludes resolved issues from all counts', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->resolved()->create([
        'severity' => IssueSeverity::Critical,
        'risk_weight' => 100,
        'occurrence_count' => 10,
        'first_detected_at' => now()->subDays(10),
        'last_detected_at' => now()->subDays(5),
    ]);

    $breakdown = $this->service->handle($this->organization);

    expect($breakdown['total_risk_score'])->toBe(0)
        ->and($breakdown['open_issues'])->toBe(0)
        ->and($breakdown['severity_distribution']['critical']['count'])->toBe(0);
});

it('calculates aging_distribution correctly', function (): void {
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

    $breakdown = $this->service->handle($this->organization);

    expect($breakdown['aging_distribution']['under_30_days'])->toBe(1)
        ->and($breakdown['aging_distribution']['30_to_60_days'])->toBe(1)
        ->and($breakdown['aging_distribution']['over_60_days'])->toBe(1);
});

it('returns highest_risk_rules ordered by risk contribution descending', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'rule_key' => 'wcag-1.1.1',
        'risk_weight' => 50,
        'occurrence_count' => 2,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'rule_key' => 'wcag-2.4.4',
        'risk_weight' => 80,
        'occurrence_count' => 3,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $breakdown = $this->service->handle($this->organization);

    expect($breakdown['highest_risk_rules'])->toHaveCount(2)
        ->and($breakdown['highest_risk_rules'][0]['rule_key'])->toBe('wcag-2.4.4')
        ->and($breakdown['highest_risk_rules'][0]['risk_contribution'])->toBe(240)
        ->and($breakdown['highest_risk_rules'][1]['rule_key'])->toBe('wcag-1.1.1')
        ->and($breakdown['highest_risk_rules'][1]['risk_contribution'])->toBe(100);
});

it('aggregates multiple issues with the same rule_key', function (): void {
    Issue::factory(3)->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'rule_key' => 'wcag-1.4.3',
        'risk_weight' => 10,
        'occurrence_count' => 1,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $breakdown = $this->service->handle($this->organization);

    expect($breakdown['highest_risk_rules'])->toHaveCount(1)
        ->and($breakdown['highest_risk_rules'][0]['rule_key'])->toBe('wcag-1.4.3')
        ->and($breakdown['highest_risk_rules'][0]['issue_count'])->toBe(3)
        ->and($breakdown['highest_risk_rules'][0]['risk_contribution'])->toBe(30);
});

it('does not include data from other organizations', function (): void {
    $otherOrganization = Organization::factory()->create(['agency_id' => $this->agency->id]);

    Issue::factory()->for($this->agency)->for($otherOrganization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 100,
        'occurrence_count' => 10,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $breakdown = $this->service->handle($this->organization);

    expect($breakdown['total_risk_score'])->toBe(0)
        ->and($breakdown['open_issues'])->toBe(0);
});

it('includes a generated_at ISO 8601 timestamp', function (): void {
    $breakdown = $this->service->handle($this->organization);

    expect($breakdown['generated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});
