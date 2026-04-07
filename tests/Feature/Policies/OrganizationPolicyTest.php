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

it('allows any user to viewAny organizations', function (): void {
    expect($this->viewer->can('viewAny', Organization::class))->toBeTrue();
    expect($this->otherAgencyUser->can('viewAny', Organization::class))->toBeTrue();
});

// ─── view ─────────────────────────────────────────────────────────────────────

it('allows same-agency user to view an organization', function (): void {
    expect($this->viewer->can('view', $this->organization))->toBeTrue();
    expect($this->editor->can('view', $this->organization))->toBeTrue();
});

it('allows superUser to view any organization', function (): void {
    expect($this->superUser->can('view', $this->organization))->toBeTrue();
});

it('forbids other-agency user from viewing an organization', function (): void {
    expect($this->otherAgencyUser->cannot('view', $this->organization))->toBeTrue();
});

// ─── create ──────────────────────────────────────────────────────────────────

it('allows agencyAdmin or higher to create an organization', function (): void {
    expect($this->agencyAdmin->can('create', Organization::class))->toBeTrue();
    expect($this->superUser->can('create', Organization::class))->toBeTrue();
});

it('forbids orgAdmin from creating an organization', function (): void {
    expect($this->orgAdmin->cannot('create', Organization::class))->toBeTrue();
});

it('forbids propAdmin from creating an organization', function (): void {
    expect($this->propAdmin->cannot('create', Organization::class))->toBeTrue();
});

it('forbids editor from creating an organization', function (): void {
    expect($this->editor->cannot('create', Organization::class))->toBeTrue();
});

it('forbids viewer from creating an organization', function (): void {
    expect($this->viewer->cannot('create', Organization::class))->toBeTrue();
});

// ─── update ──────────────────────────────────────────────────────────────────

it('allows orgAdmin or higher to update an organization', function (): void {
    expect($this->orgAdmin->can('update', $this->organization))->toBeTrue();
    expect($this->agencyAdmin->can('update', $this->organization))->toBeTrue();
    expect($this->superUser->can('update', $this->organization))->toBeTrue();
});

it('forbids propAdmin from updating an organization', function (): void {
    expect($this->propAdmin->cannot('update', $this->organization))->toBeTrue();
});

it('forbids viewer from updating an organization', function (): void {
    expect($this->viewer->cannot('update', $this->organization))->toBeTrue();
});

// ─── delete ──────────────────────────────────────────────────────────────────

it('allows agencyAdmin or higher to delete an organization', function (): void {
    expect($this->agencyAdmin->can('delete', $this->organization))->toBeTrue();
    expect($this->superUser->can('delete', $this->organization))->toBeTrue();
});

it('forbids orgAdmin from deleting an organization', function (): void {
    expect($this->orgAdmin->cannot('delete', $this->organization))->toBeTrue();
});

it('forbids viewer from deleting an organization', function (): void {
    expect($this->viewer->cannot('delete', $this->organization))->toBeTrue();
});
