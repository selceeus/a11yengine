<?php

use App\Enums\FindingSeverity;
use App\Models\Agency;
use App\Models\Finding;
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

    $finding = Finding::factory()->for($agency)->for($scan)->for($property)->create();

    expect($finding->agency)->toBeInstanceOf(Agency::class)
        ->and($finding->agency->is($agency))->toBeTrue();
});

it('belongs to a scan', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    $finding = Finding::factory()->for($agency)->for($scan)->for($property)->create();

    expect($finding->scan)->toBeInstanceOf(Scan::class)
        ->and($finding->scan->is($scan))->toBeTrue();
});

it('belongs to a property', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    $finding = Finding::factory()->for($agency)->for($scan)->for($property)->create();

    expect($finding->property)->toBeInstanceOf(Property::class)
        ->and($finding->property->is($property))->toBeTrue();
});

it('scan has many findings', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    Finding::factory()->count(3)->for($agency)->for($scan)->for($property)->create();

    expect($scan->findings)->toHaveCount(3)
        ->each->toBeInstanceOf(Finding::class);
});

it('property has many findings', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    Finding::factory()->count(3)->for($agency)->for($scan)->for($property)->create();

    expect($property->findings)->toHaveCount(3)
        ->each->toBeInstanceOf(Finding::class);
});

it('casts severity to FindingSeverity enum', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    $finding = Finding::factory()->for($agency)->for($scan)->for($property)->create([
        'severity' => FindingSeverity::CRITICAL,
    ]);

    expect($finding->severity)->toBeInstanceOf(FindingSeverity::class)
        ->and($finding->severity)->toBe(FindingSeverity::CRITICAL);
});

it('severity enum has expected cases', function (): void {
    expect(FindingSeverity::cases())->toHaveCount(5)
        ->and(FindingSeverity::CRITICAL->value)->toBe('critical')
        ->and(FindingSeverity::SERIOUS->value)->toBe('serious')
        ->and(FindingSeverity::MODERATE->value)->toBe('moderate')
        ->and(FindingSeverity::MINOR->value)->toBe('minor')
        ->and(FindingSeverity::INFO->value)->toBe('info');
});

it('applies tenant scope to only return findings for the authenticated users agency', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $organizationA = Organization::factory()->create(['agency_id' => $agencyA->id]);
    $organizationB = Organization::factory()->create(['agency_id' => $agencyB->id]);

    $propertyA = Property::factory()->for($agencyA)->for($organizationA)->create();
    $propertyB = Property::factory()->for($agencyB)->for($organizationB)->create();

    $scanA = Scan::factory()->for($agencyA)->for($organizationA)->for($propertyA)->create();
    $scanB = Scan::factory()->for($agencyB)->for($organizationB)->for($propertyB)->create();

    $user = User::factory()->create(['agency_id' => $agencyA->id]);

    test()->actingAs($user);

    Finding::factory()->count(2)->create([
        'agency_id' => $agencyA->id,
        'scan_id' => $scanA->id,
        'property_id' => $propertyA->id,
    ]);

    Finding::factory()->create([
        'agency_id' => $agencyB->id,
        'scan_id' => $scanB->id,
        'property_id' => $propertyB->id,
    ]);

    expect(Finding::query()->count())->toBe(2)
        ->and(Finding::withoutGlobalScope(TenantScope::class)->count())->toBe(3);
});

it('tenant scope does not return findings from another agency', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $organizationB = Organization::factory()->create(['agency_id' => $agencyB->id]);
    $propertyB = Property::factory()->for($agencyB)->for($organizationB)->create();
    $scanB = Scan::factory()->for($agencyB)->for($organizationB)->for($propertyB)->create();

    $user = User::factory()->create(['agency_id' => $agencyA->id]);

    test()->actingAs($user);

    Finding::factory()->create([
        'agency_id' => $agencyB->id,
        'scan_id' => $scanB->id,
        'property_id' => $propertyB->id,
    ]);

    expect(Finding::query()->count())->toBe(0);
});
