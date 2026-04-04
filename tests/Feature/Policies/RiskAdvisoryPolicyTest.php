<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\RiskAdvisory;
use App\Models\User;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->otherAgency = Agency::factory()->create();

    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);

    $this->advisory = RiskAdvisory::factory()->create([
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

it('allows any user to viewAny risk advisories', function (): void {
    expect($this->viewer->can('viewAny', RiskAdvisory::class))->toBeTrue();
    expect($this->otherAgencyUser->can('viewAny', RiskAdvisory::class))->toBeTrue();
});

// ─── view ─────────────────────────────────────────────────────────────────────

it('allows same-agency user to view a risk advisory', function (): void {
    expect($this->viewer->can('view', $this->advisory))->toBeTrue();
    expect($this->editor->can('view', $this->advisory))->toBeTrue();
});

it('forbids other-agency user from viewing a risk advisory', function (): void {
    expect($this->otherAgencyUser->cannot('view', $this->advisory))->toBeTrue();
});

// ─── create ──────────────────────────────────────────────────────────────────

it('allows editor or higher to create a risk advisory for a property', function (): void {
    expect($this->editor->can('create', [RiskAdvisory::class, $this->property]))->toBeTrue();
    expect($this->propAdmin->can('create', [RiskAdvisory::class, $this->property]))->toBeTrue();
    expect($this->orgAdmin->can('create', [RiskAdvisory::class, $this->property]))->toBeTrue();
    expect($this->agencyAdmin->can('create', [RiskAdvisory::class, $this->property]))->toBeTrue();
    expect($this->superUser->can('create', [RiskAdvisory::class, $this->property]))->toBeTrue();
});

it('forbids a viewer from creating a risk advisory', function (): void {
    expect($this->viewer->cannot('create', [RiskAdvisory::class, $this->property]))->toBeTrue();
});

// ─── delete ──────────────────────────────────────────────────────────────────

it('allows propAdmin or higher to delete a risk advisory', function (): void {
    expect($this->propAdmin->can('delete', $this->advisory))->toBeTrue();
    expect($this->orgAdmin->can('delete', $this->advisory))->toBeTrue();
    expect($this->agencyAdmin->can('delete', $this->advisory))->toBeTrue();
    expect($this->superUser->can('delete', $this->advisory))->toBeTrue();
});

it('forbids an editor from deleting a risk advisory', function (): void {
    expect($this->editor->cannot('delete', $this->advisory))->toBeTrue();
});

it('forbids a viewer from deleting a risk advisory', function (): void {
    expect($this->viewer->cannot('delete', $this->advisory))->toBeTrue();
});
