<?php

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Finding;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;
use App\Models\UserRole as UserRoleModel;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function createTenant(): Agency
{
    return Agency::factory()->create();
}

function createUser(Agency $agency): User
{
    return User::factory()->create(['agency_id' => $agency->id]);
}

// ---------------------------------------------------------------------------
// Organization
// ---------------------------------------------------------------------------

it('filters organizations to the authenticated users agency', function (): void {
    $agencyA = createTenant();
    $agencyB = createTenant();

    Organization::factory()->create(['agency_id' => $agencyA->id]);
    Organization::factory()->create(['agency_id' => $agencyB->id]);

    $this->actingAs(createUser($agencyA));

    expect(Organization::query()->count())->toBe(1);
});

it('filters organizations by the container-bound agency (SetTenant)', function (): void {
    $agencyA = createTenant();
    $agencyB = createTenant();

    Organization::factory()->create(['agency_id' => $agencyA->id]);
    Organization::factory()->create(['agency_id' => $agencyB->id]);

    // Simulate SetTenant / SetCurrentAgency binding without HTTP middleware.
    app()->instance(Agency::class, $agencyA);

    expect(Organization::query()->count())->toBe(1)
        ->and(Organization::query()->value('agency_id'))->toBe($agencyA->id);
});

// ---------------------------------------------------------------------------
// Property
// ---------------------------------------------------------------------------

it('filters properties to the authenticated users agency', function (): void {
    $agencyA = createTenant();
    $agencyB = createTenant();
    $orgA = Organization::factory()->create(['agency_id' => $agencyA->id]);
    $orgB = Organization::factory()->create(['agency_id' => $agencyB->id]);

    Property::factory()->create(['agency_id' => $agencyA->id, 'organization_id' => $orgA->id]);
    Property::factory()->create(['agency_id' => $agencyB->id, 'organization_id' => $orgB->id]);

    $this->actingAs(createUser($agencyA));

    expect(Property::query()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Scan
// ---------------------------------------------------------------------------

it('filters scans to the authenticated users agency', function (): void {
    $agencyA = createTenant();
    $agencyB = createTenant();
    $orgA = Organization::factory()->create(['agency_id' => $agencyA->id]);
    $orgB = Organization::factory()->create(['agency_id' => $agencyB->id]);
    $propA = Property::factory()->create(['agency_id' => $agencyA->id, 'organization_id' => $orgA->id]);
    $propB = Property::factory()->create(['agency_id' => $agencyB->id, 'organization_id' => $orgB->id]);

    Scan::factory()->create(['agency_id' => $agencyA->id, 'organization_id' => $orgA->id, 'property_id' => $propA->id]);
    Scan::factory()->create(['agency_id' => $agencyB->id, 'organization_id' => $orgB->id, 'property_id' => $propB->id]);

    $this->actingAs(createUser($agencyA));

    expect(Scan::query()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Issue
// ---------------------------------------------------------------------------

it('filters issues to the authenticated users agency', function (): void {
    $agencyA = createTenant();
    $agencyB = createTenant();
    $orgA = Organization::factory()->create(['agency_id' => $agencyA->id]);
    $orgB = Organization::factory()->create(['agency_id' => $agencyB->id]);
    $propA = Property::factory()->create(['agency_id' => $agencyA->id, 'organization_id' => $orgA->id]);
    $propB = Property::factory()->create(['agency_id' => $agencyB->id, 'organization_id' => $orgB->id]);

    Issue::factory()->create(['agency_id' => $agencyA->id, 'organization_id' => $orgA->id, 'property_id' => $propA->id]);
    Issue::factory()->create(['agency_id' => $agencyB->id, 'organization_id' => $orgB->id, 'property_id' => $propB->id]);

    $this->actingAs(createUser($agencyA));

    expect(Issue::query()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Finding
// ---------------------------------------------------------------------------

it('filters findings to the authenticated users agency', function (): void {
    $agencyA = createTenant();
    $agencyB = createTenant();
    $orgA = Organization::factory()->create(['agency_id' => $agencyA->id]);
    $orgB = Organization::factory()->create(['agency_id' => $agencyB->id]);
    $propA = Property::factory()->create(['agency_id' => $agencyA->id, 'organization_id' => $orgA->id]);
    $propB = Property::factory()->create(['agency_id' => $agencyB->id, 'organization_id' => $orgB->id]);

    Finding::factory()->create(['agency_id' => $agencyA->id, 'property_id' => $propA->id]);
    Finding::factory()->create(['agency_id' => $agencyB->id, 'property_id' => $propB->id]);

    $this->actingAs(createUser($agencyA));

    expect(Finding::query()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Super User bypass
// ---------------------------------------------------------------------------

it('does not filter when the authenticated user is a super user', function (): void {
    $agencyA = createTenant();
    $agencyB = createTenant();

    Organization::factory()->create(['agency_id' => $agencyA->id]);
    Organization::factory()->create(['agency_id' => $agencyB->id]);

    $superUser = User::factory()->create(['agency_id' => $agencyA->id]);
    UserRoleModel::factory()->create([
        'user_id' => $superUser->id,
        'role' => UserRole::SuperUser->value,
    ]);

    $this->actingAs($superUser);

    expect(Organization::query()->count())->toBe(2);
});

it('does not filter any model for a super user', function (): void {
    $agencyA = createTenant();
    $agencyB = createTenant();
    $orgA = Organization::factory()->create(['agency_id' => $agencyA->id]);
    $orgB = Organization::factory()->create(['agency_id' => $agencyB->id]);

    Property::factory()->create(['agency_id' => $agencyA->id, 'organization_id' => $orgA->id]);
    Property::factory()->create(['agency_id' => $agencyB->id, 'organization_id' => $orgB->id]);

    $superUser = User::factory()->create(['agency_id' => $agencyA->id]);
    UserRoleModel::factory()->create([
        'user_id' => $superUser->id,
        'role' => UserRole::SuperUser->value,
    ]);

    $this->actingAs($superUser);

    expect(Property::query()->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// No authentication and no container binding — no filter applied
// ---------------------------------------------------------------------------

it('does not filter when there is no authenticated user and no container binding', function (): void {
    $agencyA = createTenant();
    $agencyB = createTenant();

    Organization::factory()->create(['agency_id' => $agencyA->id]);
    Organization::factory()->create(['agency_id' => $agencyB->id]);

    // No actingAs(), no app()->instance() binding.
    expect(Organization::query()->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// currentAgency() helper
// ---------------------------------------------------------------------------

it('currentAgency() returns null when no agency is bound', function (): void {
    expect(currentAgency())->toBeNull();
});

it('currentAgency() returns the bound agency from the container', function (): void {
    $agency = createTenant();
    app()->instance(Agency::class, $agency);

    expect(currentAgency())->toBeInstanceOf(Agency::class)
        ->and(currentAgency()->id)->toBe($agency->id);
});
