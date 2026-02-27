<?php

use App\Domain\Risk\RecordOrganizationRiskSnapshot;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\OrganizationRiskSnapshot;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->service = app(RecordOrganizationRiskSnapshot::class);
});

it('persists a snapshot with the calculated risk score', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 10,
        'occurrence_count' => 3,
    ]);

    $snapshot = $this->service->handle($this->organization);

    expect(OrganizationRiskSnapshot::query()->count())->toBe(1)
        ->and($snapshot->organization_id)->toBe($this->organization->id)
        ->and($snapshot->risk_score)->toBe(30)
        ->and($snapshot->calculated_at)->not->toBeNull();
});

it('returns an OrganizationRiskSnapshot model instance', function (): void {
    $snapshot = $this->service->handle($this->organization);

    expect($snapshot)->toBeInstanceOf(OrganizationRiskSnapshot::class);
});

it('records a score of zero when there are no open issues', function (): void {
    $snapshot = $this->service->handle($this->organization);

    expect($snapshot->risk_score)->toBe(0);
});

it('accepts an organization id instead of a model', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->create([
        'status' => IssueStatus::Open,
        'risk_weight' => 5,
        'occurrence_count' => 4,
    ]);

    $snapshot = $this->service->handle($this->organization->id);

    expect($snapshot->organization_id)->toBe($this->organization->id)
        ->and($snapshot->risk_score)->toBe(20);
});

it('can record multiple snapshots over time', function (): void {
    $this->service->handle($this->organization);
    $this->service->handle($this->organization);

    expect(OrganizationRiskSnapshot::query()->count())->toBe(2);
});
