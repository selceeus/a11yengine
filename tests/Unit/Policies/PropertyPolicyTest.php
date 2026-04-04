<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->otherAgency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);
});

// ─── viewAny / create ────────────────────────────────────────────────────────

it('allows any authenticated user to view the property list', function (): void {
    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    expect($user->can('viewAny', Property::class))->toBeTrue();
});

it('allows an org admin to create a property in their org', function (): void {
    $user = User::factory()->withRole(UserRoleEnum::OrgAdmin, orgId: $this->organization->id)->create(['agency_id' => $this->agency->id]);
    expect($user->can('create', [Property::class, $this->organization]))->toBeTrue();
});

it('denies a viewer from creating a property', function (): void {
    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    expect($user->can('create', [Property::class, $this->organization]))->toBeFalse();
});

// ─── view ────────────────────────────────────────────────────────────────────

it('allows a user to view a property belonging to their agency', function (): void {
    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    expect($user->can('view', $this->property))->toBeTrue();
});

it('denies a user from viewing a property belonging to another agency', function (): void {
    $user = User::factory()->create(['agency_id' => $this->otherAgency->id]);
    expect($user->can('view', $this->property))->toBeFalse();
});

// ─── update ──────────────────────────────────────────────────────────────────

it('allows a property admin to update a property', function (): void {
    $user = User::factory()->withRole(UserRoleEnum::PropAdmin, propertyId: $this->property->id)->create(['agency_id' => $this->agency->id]);
    expect($user->can('update', $this->property))->toBeTrue();
});

it('denies a viewer from updating a property', function (): void {
    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    expect($user->can('update', $this->property))->toBeFalse();
});

it('denies a user from updating a property belonging to another agency', function (): void {
    $otherProperty = Property::factory()->create(['agency_id' => $this->otherAgency->id]);
    $user = User::factory()->withRole(UserRoleEnum::PropAdmin, propertyId: $otherProperty->id)->create(['agency_id' => $this->otherAgency->id]);
    expect($user->can('update', $this->property))->toBeFalse();
});

// ─── delete ──────────────────────────────────────────────────────────────────

it('allows an org admin to delete a property in their org', function (): void {
    $user = User::factory()->withRole(UserRoleEnum::OrgAdmin, orgId: $this->organization->id)->create(['agency_id' => $this->agency->id]);
    expect($user->can('delete', $this->property))->toBeTrue();
});

it('denies a viewer from deleting a property', function (): void {
    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    expect($user->can('delete', $this->property))->toBeFalse();
});

it('denies a user from deleting a property belonging to another agency', function (): void {
    $otherOrg = Organization::factory()->create(['agency_id' => $this->otherAgency->id]);
    $user = User::factory()->withRole(UserRoleEnum::OrgAdmin, orgId: $otherOrg->id)->create(['agency_id' => $this->otherAgency->id]);
    expect($user->can('delete', $this->property))->toBeFalse();
});
