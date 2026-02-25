<?php

use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\Scopes\TenantScope;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('belongs to an agency', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();

    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    expect($scan->agency)->toBeInstanceOf(Agency::class)
        ->and($scan->agency->is($agency))->toBeTrue();
});

it('belongs to an organization', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();

    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    expect($scan->organization)->toBeInstanceOf(Organization::class)
        ->and($scan->organization->is($organization))->toBeTrue();
});

it('belongs to a property', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();

    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    expect($scan->property)->toBeInstanceOf(Property::class)
        ->and($scan->property->is($property))->toBeTrue();
});

it('property has many scans', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();

    Scan::factory()->count(3)->for($agency)->for($organization)->for($property)->create();

    expect($property->scans)->toHaveCount(3)
        ->each->toBeInstanceOf(Scan::class);
});

it('applies tenant scope to only return scans for the authenticated users agency', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $organizationA = Organization::factory()->create(['agency_id' => $agencyA->id]);
    $organizationB = Organization::factory()->create(['agency_id' => $agencyB->id]);

    $propertyA = Property::factory()->for($agencyA)->for($organizationA)->create();
    $propertyB = Property::factory()->for($agencyB)->for($organizationB)->create();

    $user = User::factory()->create(['agency_id' => $agencyA->id]);

    test()->actingAs($user);

    Scan::factory()->count(2)->create([
        'agency_id' => $agencyA->id,
        'organization_id' => $organizationA->id,
        'property_id' => $propertyA->id,
    ]);

    Scan::factory()->create([
        'agency_id' => $agencyB->id,
        'organization_id' => $organizationB->id,
        'property_id' => $propertyB->id,
    ]);

    expect(Scan::query()->count())->toBe(2)
        ->and(Scan::withoutGlobalScope(TenantScope::class)->count())->toBe(3);
});

it('tenant scope does not return scans from another agency', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $organizationB = Organization::factory()->create(['agency_id' => $agencyB->id]);
    $propertyB = Property::factory()->for($agencyB)->for($organizationB)->create();

    $user = User::factory()->create(['agency_id' => $agencyA->id]);

    test()->actingAs($user);

    Scan::factory()->create([
        'agency_id' => $agencyB->id,
        'organization_id' => $organizationB->id,
        'property_id' => $propertyB->id,
    ]);

    expect(Scan::query()->count())->toBe(0);
});

it('is mass assignable with expected fillable fields', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();

    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create([
        'status' => 'completed',
        'started_at' => '2026-02-24 10:00:00',
        'completed_at' => '2026-02-24 10:05:00',
        'raw_summary' => ['issues' => 3],
    ]);

    expect($scan->status)->toBe('completed')
        ->and($scan->raw_summary)->toBe(['issues' => 3])
        ->and($scan->started_at)->not->toBeNull()
        ->and($scan->completed_at)->not->toBeNull();
});
