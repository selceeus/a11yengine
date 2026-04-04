<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Agency;
use App\Models\Issue;
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

    $this->issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
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

it('allows any authenticated user to viewAny issues', function (): void {
    expect($this->viewer->can('viewAny', Issue::class))->toBeTrue();
    expect($this->editor->can('viewAny', Issue::class))->toBeTrue();
    expect($this->otherAgencyUser->can('viewAny', Issue::class))->toBeTrue();
});

// ─── view ─────────────────────────────────────────────────────────────────────

it('allows same-agency user to view an issue', function (): void {
    expect($this->viewer->can('view', $this->issue))->toBeTrue();
    expect($this->editor->can('view', $this->issue))->toBeTrue();
});

it('forbids other-agency user from viewing an issue', function (): void {
    expect($this->otherAgencyUser->cannot('view', $this->issue))->toBeTrue();
});

// ─── update ──────────────────────────────────────────────────────────────────

it('allows editor or higher to update an issue', function (): void {
    expect($this->editor->can('update', $this->issue))->toBeTrue();
    expect($this->propAdmin->can('update', $this->issue))->toBeTrue();
    expect($this->orgAdmin->can('update', $this->issue))->toBeTrue();
    expect($this->agencyAdmin->can('update', $this->issue))->toBeTrue();
    expect($this->superUser->can('update', $this->issue))->toBeTrue();
});

it('forbids a viewer from updating an issue', function (): void {
    expect($this->viewer->cannot('update', $this->issue))->toBeTrue();
});

it('forbids other-agency user from updating an issue', function (): void {
    expect($this->otherAgencyUser->cannot('update', $this->issue))->toBeTrue();
});

// ─── delete ──────────────────────────────────────────────────────────────────

it('allows propAdmin or higher to delete an issue', function (): void {
    expect($this->propAdmin->can('delete', $this->issue))->toBeTrue();
    expect($this->orgAdmin->can('delete', $this->issue))->toBeTrue();
    expect($this->agencyAdmin->can('delete', $this->issue))->toBeTrue();
    expect($this->superUser->can('delete', $this->issue))->toBeTrue();
});

it('forbids an editor from deleting an issue', function (): void {
    expect($this->editor->cannot('delete', $this->issue))->toBeTrue();
});

it('forbids a viewer from deleting an issue', function (): void {
    expect($this->viewer->cannot('delete', $this->issue))->toBeTrue();
});
