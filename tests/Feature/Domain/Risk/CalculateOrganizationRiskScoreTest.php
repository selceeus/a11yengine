<?php

use App\Domain\Risk\CalculateOrganizationRiskScore;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Date;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->service = new CalculateOrganizationRiskScore;
});

it('returns zero when there are no open issues', function (): void {
    expect($this->service->handle($this->organization))->toBe(0);
});

it('sums risk_weight multiplied by occurrence_count for open issues', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 10,
        'occurrence_count' => 3,
    ]);

    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 5,
        'occurrence_count' => 4,
    ]);

    // (10 * 3) + (5 * 4) = 50
    expect($this->service->handle($this->organization))->toBe(50);
});

it('excludes non-open issues from the score', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 10,
        'occurrence_count' => 2,
    ]);

    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Resolved,
        'risk_weight' => 100,
        'occurrence_count' => 5,
    ]);

    // 10 * 2 = 20 (resolved issue ignored)
    expect($this->service->handle($this->organization))->toBe(20);
});

it('accepts an organization id instead of a model', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 8,
        'occurrence_count' => 5,
    ]);

    expect($this->service->handle($this->organization->id))->toBe(40);
});

it('only scores issues belonging to the given organization', function (): void {
    $otherOrganization = Organization::factory()->create(['agency_id' => $this->agency->id]);

    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 10,
        'occurrence_count' => 2,
    ]);

    Issue::factory()->for($this->agency)->for($otherOrganization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 50,
        'occurrence_count' => 10,
    ]);

    expect($this->service->handle($this->organization))->toBe(20);
});

// openIssueCount

it('openIssueCount returns zero when there are no open issues', function (): void {
    expect($this->service->openIssueCount($this->organization->id))->toBe(0);
});

it('openIssueCount counts only active-status issues', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Resolved,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    expect($this->service->openIssueCount($this->organization->id))->toBe(1);
});

it('openIssueCount only counts issues for the given organization', function (): void {
    $other = Organization::factory()->create(['agency_id' => $this->agency->id]);

    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);
    Issue::factory()->for($this->agency)->for($other)->create([
        'status' => IssueStatus::Open,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    expect($this->service->openIssueCount($this->organization->id))->toBe(1);
});

// agingBuckets

it('agingBuckets returns all-zero buckets when there are no issues', function (): void {
    expect($this->service->agingBuckets($this->organization->id))->toBe([
        'under_30_days' => 0,
        '30_to_60_days' => 0,
        'over_60_days' => 0,
    ]);
});

it('agingBuckets places issues in the correct bucket based on first_detected_at', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'first_detected_at' => Date::now()->subDays(10),
        'last_detected_at' => now(),
    ]);
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'first_detected_at' => Date::now()->subDays(45),
        'last_detected_at' => now(),
    ]);
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'first_detected_at' => Date::now()->subDays(90),
        'last_detected_at' => now(),
    ]);

    expect($this->service->agingBuckets($this->organization->id))->toBe([
        'under_30_days' => 1,
        '30_to_60_days' => 1,
        'over_60_days' => 1,
    ]);
});

it('agingBuckets excludes resolved issues', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Resolved,
        'first_detected_at' => Date::now()->subDays(10),
        'last_detected_at' => now(),
    ]);

    expect($this->service->agingBuckets($this->organization->id))->toBe([
        'under_30_days' => 0,
        '30_to_60_days' => 0,
        'over_60_days' => 0,
    ]);
});
