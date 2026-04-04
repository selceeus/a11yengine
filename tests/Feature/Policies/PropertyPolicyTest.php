<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->otherAgency = Agency::factory()->create();

    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);

    $this->superUser = User::factory()->withRole(UserRoleEnum::SuperUser)->create(['agency_id' => $this->agency->id]);
    $this->agencyAdmin = User::factory()->withRole(UserRoleEnum::AgencyAdmin, agencyId: $this->agency->id)->create(['agency_id' => $this->agency->id]);
    $this->orgAdmin = User::factory()->withRole(UserRoleEnum::OrgAdmin, orgId: $this->organization->id)->create(['agency_id' => $this->agency->id]);
    $this->propAdmin = User::factory()->withRole(UserRoleEnum::PropAdmin, propertyId: $this->property->id)->create(['agency_id' => $this->agency->id]);
    $this->editor = User::factory()->withRole(UserRoleEnum::Editor, propertyId: $this->property->id)->create(['agency_id' => $this->agency->id]);
    $this->viewer = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->otherAgencyUser = User::factory()->create(['agency_id' => $this->otherAgency->id]);
});

// ─── viewAny ─────────────────────────────────────────────────────────────────

it('allows any user to viewAny properties', function (): void {
    expect($this->viewer->can('viewAny', Property::class))->toBeTrue();
    expect($this->otherAgencyUser->can('viewAny', Property::class))->toBeTrue();
});

// ─── view ─────────────────────────────────────────────────────────────────────

it('allows same-agency user to view a property', function (): void {
    expect($this->viewer->can('view', $this->property))->toBeTrue();
    expect($this->editor->can('view', $this->property))->toBeTrue();
});

it('forbids other-agency user from viewing a property', function (): void {
    expect($this->otherAgencyUser->cannot('view', $this->property))->toBeTrue();
});

// ─── create (with org context) ───────────────────────────────────────────────

it('allows orgAdmin or higher to create a property in an org', function (): void {
    expect($this->orgAdmin->can('create', [Property::class, $this->organization]))->toBeTrue();
    expect($this->agencyAdmin->can('create', [Property::class, $this->organization]))->toBeTrue();
    expect($this->superUser->can('create', [Property::class, $this->organization]))->toBeTrue();
});

it('forbids propAdmin from creating a property in an org', function (): void {
    expect($this->propAdmin->cannot('create', [Property::class, $this->organization]))->toBeTrue();
});

it('forbids viewer from creating a property in an org', function (): void {
    expect($this->viewer->cannot('create', [Property::class, $this->organization]))->toBeTrue();
});

// ─── create (broad check, no org context) ────────────────────────────────────

it('allows agencyAdmin or orgAdmin for the broad create check', function (): void {
    expect($this->agencyAdmin->can('create', Property::class))->toBeTrue();
    expect($this->orgAdmin->can('create', Property::class))->toBeTrue();
    expect($this->superUser->can('create', Property::class))->toBeTrue();
});

it('forbids propAdmin for the broad create check', function (): void {
    expect($this->propAdmin->cannot('create', Property::class))->toBeTrue();
});

it('forbids viewer for the broad create check', function (): void {
    expect($this->viewer->cannot('create', Property::class))->toBeTrue();
});

// ─── update ──────────────────────────────────────────────────────────────────

it('allows propAdmin or higher to update a property', function (): void {
    expect($this->propAdmin->can('update', $this->property))->toBeTrue();
    expect($this->orgAdmin->can('update', $this->property))->toBeTrue();
    expect($this->agencyAdmin->can('update', $this->property))->toBeTrue();
    expect($this->superUser->can('update', $this->property))->toBeTrue();
});

it('forbids editor from updating a property', function (): void {
    expect($this->editor->cannot('update', $this->property))->toBeTrue();
});

it('forbids viewer from updating a property', function (): void {
    expect($this->viewer->cannot('update', $this->property))->toBeTrue();
});

// ─── delete ──────────────────────────────────────────────────────────────────

it('allows orgAdmin or higher to delete a property', function (): void {
    expect($this->orgAdmin->can('delete', $this->property))->toBeTrue();
    expect($this->agencyAdmin->can('delete', $this->property))->toBeTrue();
    expect($this->superUser->can('delete', $this->property))->toBeTrue();
});

it('forbids propAdmin from deleting a property', function (): void {
    expect($this->propAdmin->cannot('delete', $this->property))->toBeTrue();
});

it('forbids viewer from deleting a property', function (): void {
    expect($this->viewer->cannot('delete', $this->property))->toBeTrue();
});
