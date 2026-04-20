<?php

use App\Models\Agency;
use App\Models\Property;
use App\Models\PropertyRiskSnapshot;

it('creates one snapshot per property', function (): void {
    $agency = Agency::factory()->create();
    $property1 = Property::factory()->create(['agency_id' => $agency->id]);
    $property2 = Property::factory()->create(['agency_id' => $agency->id]);

    $this->artisan('snapshots:property-risk')->assertSuccessful();

    expect(PropertyRiskSnapshot::query()->count())->toBe(2)
        ->and(PropertyRiskSnapshot::query()->where('property_id', $property1->id)->count())->toBe(1)
        ->and(PropertyRiskSnapshot::query()->where('property_id', $property2->id)->count())->toBe(1);
});

it('records a zero risk score when the property has no issues', function (): void {
    $agency = Agency::factory()->create();
    Property::factory()->create(['agency_id' => $agency->id]);

    $this->artisan('snapshots:property-risk')->assertSuccessful();

    $snapshot = PropertyRiskSnapshot::query()->sole();

    expect($snapshot->risk_score)->toBe(0);
});

it('succeeds with an info message when there are no properties', function (): void {
    $this->artisan('snapshots:property-risk')
        ->expectsOutput('No properties found — nothing to snapshot.')
        ->assertSuccessful();

    expect(PropertyRiskSnapshot::query()->count())->toBe(0);
});

it('snapshots properties across multiple agencies', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();
    Property::factory()->create(['agency_id' => $agencyA->id]);
    Property::factory()->create(['agency_id' => $agencyB->id]);

    $this->artisan('snapshots:property-risk')->assertSuccessful();

    expect(PropertyRiskSnapshot::query()->count())->toBe(2);
});
