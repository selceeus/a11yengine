<?php

use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scopes\TenantScope;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('belongs to an agency', function (): void {
    $agency = Agency::factory()->create();

    $organization = Organization::factory()->create([
        'agency_id' => $agency->id,
    ]);

    $property = Property::factory()
        ->for($agency)
        ->for($organization)
        ->create();

    expect($property->agency)->toBeInstanceOf(Agency::class)
        ->and($property->agency->is($agency))->toBeTrue();
});

it('belongs to an organization', function (): void {
    $agency = Agency::factory()->create();

    $organization = Organization::factory()->create([
        'agency_id' => $agency->id,
    ]);

    $property = Property::factory()
        ->for($agency)
        ->for($organization)
        ->create();

    expect($property->organization)->toBeInstanceOf(Organization::class)
        ->and($property->organization->is($organization))->toBeTrue();
});

it('organization has many properties', function (): void {
    $agency = Agency::factory()->create();

    $organization = Organization::factory()->create([
        'agency_id' => $agency->id,
    ]);

    Property::factory()->count(3)
        ->for($agency)
        ->for($organization)
        ->create();

    expect($organization->properties)->toHaveCount(3)
        ->each->toBeInstanceOf(Property::class);
});

it('agency has many properties', function (): void {
    $agency = Agency::factory()->create();

    $organization = Organization::factory()->create([
        'agency_id' => $agency->id,
    ]);

    Property::factory()->count(3)
        ->for($agency)
        ->for($organization)
        ->create();

    expect($agency->properties)->toHaveCount(3)
        ->each->toBeInstanceOf(Property::class);
});

it('applies tenant scope to only return properties for the authenticated users agency', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $organizationA = Organization::factory()->create(['agency_id' => $agencyA->id]);
    $organizationB = Organization::factory()->create(['agency_id' => $agencyB->id]);

    $user = User::factory()->create(['agency_id' => $agencyA->id]);

    test()->actingAs($user);

    Property::factory()->count(2)->create([
        'agency_id' => $agencyA->id,
        'organization_id' => $organizationA->id,
    ]);

    Property::factory()->create([
        'agency_id' => $agencyB->id,
        'organization_id' => $organizationB->id,
    ]);

    expect(Property::query()->count())->toBe(2)
        ->and(Property::withoutGlobalScope(TenantScope::class)->count())->toBe(3);
});

it('tenant scope does not return properties from another agency', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $organizationA = Organization::factory()->create(['agency_id' => $agencyA->id]);
    $organizationB = Organization::factory()->create(['agency_id' => $agencyB->id]);

    $user = User::factory()->create(['agency_id' => $agencyA->id]);

    test()->actingAs($user);

    Property::factory()->create([
        'agency_id' => $agencyB->id,
        'organization_id' => $organizationB->id,
    ]);

    expect(Property::query()->count())->toBe(0);
});

it('is mass assignable with expected fillable fields', function (): void {
    $agency = Agency::factory()->create();

    $organization = Organization::factory()->create([
        'agency_id' => $agency->id,
    ]);

    $property = Property::factory()
        ->for($agency)
        ->for($organization)
        ->create([
            'name' => 'Test Property',
            'base_url' => 'https://example.com',
            'status' => 'active',
        ]);

    expect($property->name)->toBe('Test Property')
        ->and($property->base_url)->toBe('https://example.com')
        ->and($property->status)->toBe('active');
});
