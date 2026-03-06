<?php

use App\Domain\Risk\RollUpAgencyRiskSnapshots;
use App\Models\Agency;
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

    $this->service = app(RollUpAgencyRiskSnapshots::class);
});

it('returns zeroed summary when the agency has no snapshots', function (): void {
    $result = $this->service->handle($this->agency);

    expect($result['agency_id'])->toBe($this->agency->id)
        ->and($result['total_risk_score'])->toBe(0)
        ->and($result['total_open_issue_count'])->toBe(0)
        ->and($result['property_count'])->toBe(0)
        ->and($result['properties'])->toHaveCount(0);
});

it('aggregates snapshots across all properties in the agency', function (): void {
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
        'risk_score' => 100,
        'open_issue_count' => 5,
        'snapshot_date' => today()->toDateString(),
        'created_at' => now(),
    ]);

    PropertyRiskSnapshot::query()->create([
        'property_id' => $propertyB->id,
        'risk_score' => 200,
        'open_issue_count' => 8,
        'snapshot_date' => today()->toDateString(),
        'created_at' => now(),
    ]);

    $result = $this->service->handle($this->agency);

    expect($result['total_risk_score'])->toBe(300)
        ->and($result['total_open_issue_count'])->toBe(13)
        ->and($result['property_count'])->toBe(2)
        ->and($result['properties'])->toHaveCount(2);
});

it('uses the most recent snapshot per property on or before the given date', function (): void {
    $property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);

    PropertyRiskSnapshot::query()->create([
        'property_id' => $property->id,
        'risk_score' => 50,
        'open_issue_count' => 2,
        'snapshot_date' => today()->subDays(2)->toDateString(),
        'created_at' => now(),
    ]);

    PropertyRiskSnapshot::query()->create([
        'property_id' => $property->id,
        'risk_score' => 100,
        'open_issue_count' => 4,
        'snapshot_date' => today()->subDay()->toDateString(),
        'created_at' => now(),
    ]);

    PropertyRiskSnapshot::query()->create([
        'property_id' => $property->id,
        'risk_score' => 999,
        'open_issue_count' => 99,
        'snapshot_date' => today()->addDay()->toDateString(),
        'created_at' => now(),
    ]);

    $result = $this->service->handle($this->agency, Carbon::yesterday());

    expect($result['total_risk_score'])->toBe(100)
        ->and($result['total_open_issue_count'])->toBe(4)
        ->and($result['property_count'])->toBe(1);
});

it('excludes properties from other agencies', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    $otherProperty = Property::factory()->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
    ]);

    PropertyRiskSnapshot::query()->create([
        'property_id' => $otherProperty->id,
        'risk_score' => 500,
        'open_issue_count' => 20,
        'snapshot_date' => today()->toDateString(),
        'created_at' => now(),
    ]);

    $result = $this->service->handle($this->agency);

    expect($result['total_risk_score'])->toBe(0)
        ->and($result['property_count'])->toBe(0);
});

it('accepts an agency id instead of a model', function (): void {
    $result = $this->service->handle($this->agency->id);

    expect($result['agency_id'])->toBe($this->agency->id);
});

it('uses today as snapshot_date when no date is provided', function (): void {
    $result = $this->service->handle($this->agency);

    expect($result['snapshot_date'])->toBe(today()->toDateString());
});
