<?php

use App\Domain\Risk\RecordRiskSnapshot;
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

    $this->service = app(RecordRiskSnapshot::class);
});

it('persists a risk snapshot with the correct fields', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 10,
        'occurrence_count' => 4,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 5,
        'occurrence_count' => 2,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $snapshot = $this->service->handle($this->organization);

    expect(RiskSnapshot::query()->count())->toBe(1)
        ->and($snapshot)->toBeInstanceOf(RiskSnapshot::class)
        ->and($snapshot->agency_id)->toBe($this->agency->id)
        ->and($snapshot->organization_id)->toBe($this->organization->id)
        ->and($snapshot->total_risk_score)->toBe(50) // (10*4) + (5*2)
        ->and($snapshot->open_issue_count)->toBe(2)
        ->and($snapshot->snapshot_date->toDateString())->toBe(now()->toDateString())
        ->and($snapshot->created_at)->not->toBeNull();
});

it('records zero score and zero count when there are no open issues', function (): void {
    $snapshot = $this->service->handle($this->organization);

    expect($snapshot->total_risk_score)->toBe(0)
        ->and($snapshot->open_issue_count)->toBe(0);
});

it('excludes resolved issues from total_risk_score and open_issue_count', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Resolved,
        'risk_weight' => 100,
        'occurrence_count' => 10,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $snapshot = $this->service->handle($this->organization);

    expect($snapshot->total_risk_score)->toBe(0)
        ->and($snapshot->open_issue_count)->toBe(0);
});

it('accepts an organization id instead of a model', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 8,
        'occurrence_count' => 5,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    $snapshot = $this->service->handle($this->organization->id);

    expect($snapshot->organization_id)->toBe($this->organization->id)
        ->and($snapshot->total_risk_score)->toBe(40);
});

it('can record multiple snapshots over time for the same organization', function (): void {
    $this->service->handle($this->organization);
    $this->service->handle($this->organization);

    expect(RiskSnapshot::query()->count())->toBe(2);
});

it('is triggered after scan normalization via the risk snapshot endpoint', function (): void {
    $this->withoutMiddleware()
        ->postJson("/api/organizations/{$this->organization->id}/risk-snapshot")
        ->assertOk();

    expect(RiskSnapshot::query()->where('organization_id', $this->organization->id)->count())->toBe(1);
});
