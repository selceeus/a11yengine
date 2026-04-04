<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->otherAgency = Agency::factory()->create();

    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);

    $this->scan = Scan::factory()->create([
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

it('allows any user to viewAny scans', function (): void {
    expect($this->viewer->can('viewAny', Scan::class))->toBeTrue();
    expect($this->otherAgencyUser->can('viewAny', Scan::class))->toBeTrue();
});

// ─── view ─────────────────────────────────────────────────────────────────────

it('allows same-agency user to view a scan', function (): void {
    expect($this->viewer->can('view', $this->scan))->toBeTrue();
    expect($this->editor->can('view', $this->scan))->toBeTrue();
});

it('forbids other-agency user from viewing a scan', function (): void {
    expect($this->otherAgencyUser->cannot('view', $this->scan))->toBeTrue();
});

// ─── create (with property context) ──────────────────────────────────────────

it('allows editor or higher to create a scan for a property', function (): void {
    expect($this->editor->can('create', [Scan::class, $this->property]))->toBeTrue();
    expect($this->propAdmin->can('create', [Scan::class, $this->property]))->toBeTrue();
    expect($this->orgAdmin->can('create', [Scan::class, $this->property]))->toBeTrue();
    expect($this->agencyAdmin->can('create', [Scan::class, $this->property]))->toBeTrue();
    expect($this->superUser->can('create', [Scan::class, $this->property]))->toBeTrue();
});

it('forbids a viewer from creating a scan for a property', function (): void {
    expect($this->viewer->cannot('create', [Scan::class, $this->property]))->toBeTrue();
});

// ─── create (broad check, no property context) ───────────────────────────────

it('allows editor or higher for the broad create check', function (): void {
    expect($this->editor->can('create', Scan::class))->toBeTrue();
    expect($this->propAdmin->can('create', Scan::class))->toBeTrue();
    expect($this->orgAdmin->can('create', Scan::class))->toBeTrue();
    expect($this->agencyAdmin->can('create', Scan::class))->toBeTrue();
    expect($this->superUser->can('create', Scan::class))->toBeTrue();
});

it('forbids a viewer for the broad create check', function (): void {
    expect($this->viewer->cannot('create', Scan::class))->toBeTrue();
});

// ─── delete ──────────────────────────────────────────────────────────────────

it('allows propAdmin or higher to delete a scan', function (): void {
    expect($this->propAdmin->can('delete', $this->scan))->toBeTrue();
    expect($this->orgAdmin->can('delete', $this->scan))->toBeTrue();
    expect($this->agencyAdmin->can('delete', $this->scan))->toBeTrue();
    expect($this->superUser->can('delete', $this->scan))->toBeTrue();
});

it('forbids an editor from deleting a scan', function (): void {
    expect($this->editor->cannot('delete', $this->scan))->toBeTrue();
});

it('forbids a viewer from deleting a scan', function (): void {
    expect($this->viewer->cannot('delete', $this->scan))->toBeTrue();
});
