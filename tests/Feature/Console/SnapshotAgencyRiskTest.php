<?php

use App\Models\Agency;
use App\Models\AgencyRiskSnapshot;
use App\Models\Organization;
use App\Models\Property;
use App\Models\PropertyRiskSnapshot;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates one snapshot per agency for todays date', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $this->artisan('snapshots:agency-risk')->assertSuccessful();

    expect(AgencyRiskSnapshot::query()->count())->toBe(2)
        ->and(AgencyRiskSnapshot::query()->where('agency_id', $agencyA->id)->count())->toBe(1)
        ->and(AgencyRiskSnapshot::query()->where('agency_id', $agencyB->id)->count())->toBe(1);
});

it('aggregates property scores into each agency snapshot', function (): void {
    $agency = Agency::factory()->create();
    $org = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->create([
        'agency_id' => $agency->id,
        'organization_id' => $org->id,
    ]);

    PropertyRiskSnapshot::query()->create([
        'property_id' => $property->id,
        'risk_score' => 150,
        'open_issue_count' => 7,
        'snapshot_date' => today()->toDateString(),
        'created_at' => now(),
    ]);

    $this->artisan('snapshots:agency-risk')->assertSuccessful();

    $snapshot = AgencyRiskSnapshot::query()->where('agency_id', $agency->id)->sole();

    expect($snapshot->risk_score)->toBe(150)
        ->and($snapshot->open_issue_count)->toBe(7);
});

it('accepts a --date option and records snapshots for that date', function (): void {
    $agency = Agency::factory()->create();
    $org = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->create([
        'agency_id' => $agency->id,
        'organization_id' => $org->id,
    ]);

    PropertyRiskSnapshot::query()->create([
        'property_id' => $property->id,
        'risk_score' => 60,
        'open_issue_count' => 3,
        'snapshot_date' => today()->subDay()->toDateString(),
        'created_at' => now(),
    ]);

    $yesterday = today()->subDay()->toDateString();

    $this->artisan('snapshots:agency-risk', ['--date' => $yesterday])->assertSuccessful();

    $snapshot = AgencyRiskSnapshot::query()->where('agency_id', $agency->id)->sole();

    expect($snapshot->snapshot_date->toDateString())->toBe($yesterday)
        ->and($snapshot->risk_score)->toBe(60);
});

it('succeeds with a message when there are no agencies', function (): void {
    $this->artisan('snapshots:agency-risk')
        ->expectsOutput('No agencies found — nothing to snapshot.')
        ->assertSuccessful();

    expect(AgencyRiskSnapshot::query()->count())->toBe(0);
});
