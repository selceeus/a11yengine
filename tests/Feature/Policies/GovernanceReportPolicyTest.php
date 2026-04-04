<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Agency;
use App\Models\GovernanceReport;
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

    $this->report = GovernanceReport::factory()->create([
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

it('allows any user to viewAny governance reports', function (): void {
    expect($this->viewer->can('viewAny', GovernanceReport::class))->toBeTrue();
    expect($this->otherAgencyUser->can('viewAny', GovernanceReport::class))->toBeTrue();
});

// ─── view ─────────────────────────────────────────────────────────────────────

it('allows same-agency user to view a governance report', function (): void {
    expect($this->viewer->can('view', $this->report))->toBeTrue();
    expect($this->editor->can('view', $this->report))->toBeTrue();
});

it('forbids other-agency user from viewing a governance report', function (): void {
    expect($this->otherAgencyUser->cannot('view', $this->report))->toBeTrue();
});

// ─── create (with property context — Editor+) ────────────────────────────────

it('allows editor or higher to create a property-scoped governance report', function (): void {
    expect($this->editor->can('create', [GovernanceReport::class, $this->property]))->toBeTrue();
    expect($this->propAdmin->can('create', [GovernanceReport::class, $this->property]))->toBeTrue();
    expect($this->orgAdmin->can('create', [GovernanceReport::class, $this->property]))->toBeTrue();
    expect($this->agencyAdmin->can('create', [GovernanceReport::class, $this->property]))->toBeTrue();
    expect($this->superUser->can('create', [GovernanceReport::class, $this->property]))->toBeTrue();
});

it('forbids a viewer from creating a property-scoped governance report', function (): void {
    expect($this->viewer->cannot('create', [GovernanceReport::class, $this->property]))->toBeTrue();
});

// ─── create (agency-level — AgencyAdmin+) ────────────────────────────────────

it('allows agencyAdmin or higher to create an agency-level governance report', function (): void {
    expect($this->agencyAdmin->can('create', GovernanceReport::class))->toBeTrue();
    expect($this->superUser->can('create', GovernanceReport::class))->toBeTrue();
});

it('forbids orgAdmin from creating an agency-level governance report', function (): void {
    expect($this->orgAdmin->cannot('create', GovernanceReport::class))->toBeTrue();
});

it('forbids editor from creating an agency-level governance report', function (): void {
    expect($this->editor->cannot('create', GovernanceReport::class))->toBeTrue();
});

// ─── delete ──────────────────────────────────────────────────────────────────

it('allows propAdmin or higher to delete a property-scoped governance report', function (): void {
    expect($this->propAdmin->can('delete', $this->report))->toBeTrue();
    expect($this->orgAdmin->can('delete', $this->report))->toBeTrue();
    expect($this->agencyAdmin->can('delete', $this->report))->toBeTrue();
    expect($this->superUser->can('delete', $this->report))->toBeTrue();
});

it('forbids an editor from deleting a governance report', function (): void {
    expect($this->editor->cannot('delete', $this->report))->toBeTrue();
});

it('forbids a viewer from deleting a governance report', function (): void {
    expect($this->viewer->cannot('delete', $this->report))->toBeTrue();
});
