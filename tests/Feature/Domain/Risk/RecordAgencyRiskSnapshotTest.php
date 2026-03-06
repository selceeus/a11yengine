<?php

use App\Domain\Risk\RecordAgencyRiskSnapshot;
use App\Models\Agency;
use App\Models\AgencyRiskSnapshot;
use App\Models\Organization;
use App\Models\Property;
use App\Models\PropertyRiskSnapshot;
use App\Models\User;
use Illuminate\Support\Carbon;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->service = app(RecordAgencyRiskSnapshot::class);
});

it('persists an agency risk snapshot with aggregated property scores', function (): void {
    $propertyA = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);
    $propertyB = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);

    PropertyRiskSnapshot::query()->create([
        'property_id' => $propertyA->id,
        'risk_score' => 120,
        'open_issue_count' => 6,
        'snapshot_date' => today()->toDateString(),
        'created_at' => now(),
    ]);

    PropertyRiskSnapshot::query()->create([
        'property_id' => $propertyB->id,
        'risk_score' => 80,
        'open_issue_count' => 4,
        'snapshot_date' => today()->toDateString(),
        'created_at' => now(),
    ]);

    $snapshot = $this->service->handle($this->agency);

    expect(AgencyRiskSnapshot::query()->count())->toBe(1)
        ->and($snapshot)->toBeInstanceOf(AgencyRiskSnapshot::class)
        ->and($snapshot->agency_id)->toBe($this->agency->id)
        ->and($snapshot->risk_score)->toBe(200)
        ->and($snapshot->open_issue_count)->toBe(10)
        ->and($snapshot->snapshot_date->toDateString())->toBe(today()->toDateString())
        ->and($snapshot->created_at)->not->toBeNull();
});

it('records zero score when no property snapshots exist', function (): void {
    $snapshot = $this->service->handle($this->agency);

    expect($snapshot->risk_score)->toBe(0)
        ->and($snapshot->open_issue_count)->toBe(0);
});

it('accepts an agency id instead of a model', function (): void {
    $snapshot = $this->service->handle($this->agency->id);

    expect($snapshot->agency_id)->toBe($this->agency->id);
});

it('uses the provided date when recording the snapshot', function (): void {
    $property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);

    PropertyRiskSnapshot::query()->create([
        'property_id' => $property->id,
        'risk_score' => 50,
        'open_issue_count' => 3,
        'snapshot_date' => today()->subDay()->toDateString(),
        'created_at' => now(),
    ]);

    $yesterday = Carbon::yesterday();
    $snapshot = $this->service->handle($this->agency, $yesterday);

    expect($snapshot->snapshot_date->toDateString())->toBe($yesterday->toDateString())
        ->and($snapshot->risk_score)->toBe(50)
        ->and($snapshot->open_issue_count)->toBe(3);
});

it('does not include property snapshots from other agencies', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    $otherProperty = Property::factory()->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
    ]);

    PropertyRiskSnapshot::query()->create([
        'property_id' => $otherProperty->id,
        'risk_score' => 999,
        'open_issue_count' => 50,
        'snapshot_date' => today()->toDateString(),
        'created_at' => now(),
    ]);

    $snapshot = $this->service->handle($this->agency);

    expect($snapshot->risk_score)->toBe(0)
        ->and($snapshot->open_issue_count)->toBe(0);
});
