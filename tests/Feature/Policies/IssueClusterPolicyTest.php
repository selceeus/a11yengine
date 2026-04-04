<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Agency;
use App\Models\IssueCluster;
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

    $this->cluster = IssueCluster::factory()->create([
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

it('allows any user to viewAny issue clusters', function (): void {
    expect($this->viewer->can('viewAny', IssueCluster::class))->toBeTrue();
    expect($this->otherAgencyUser->can('viewAny', IssueCluster::class))->toBeTrue();
});

// ─── view ─────────────────────────────────────────────────────────────────────

it('allows same-agency user to view an issue cluster', function (): void {
    expect($this->viewer->can('view', $this->cluster))->toBeTrue();
    expect($this->editor->can('view', $this->cluster))->toBeTrue();
});

it('forbids other-agency user from viewing an issue cluster', function (): void {
    expect($this->otherAgencyUser->cannot('view', $this->cluster))->toBeTrue();
});

// ─── create ──────────────────────────────────────────────────────────────────

it('allows editor or higher to create an issue cluster for a property', function (): void {
    expect($this->editor->can('create', [IssueCluster::class, $this->property]))->toBeTrue();
    expect($this->propAdmin->can('create', [IssueCluster::class, $this->property]))->toBeTrue();
    expect($this->orgAdmin->can('create', [IssueCluster::class, $this->property]))->toBeTrue();
    expect($this->agencyAdmin->can('create', [IssueCluster::class, $this->property]))->toBeTrue();
    expect($this->superUser->can('create', [IssueCluster::class, $this->property]))->toBeTrue();
});

it('forbids a viewer from creating an issue cluster', function (): void {
    expect($this->viewer->cannot('create', [IssueCluster::class, $this->property]))->toBeTrue();
});

// ─── delete ──────────────────────────────────────────────────────────────────

it('allows propAdmin or higher to delete an issue cluster', function (): void {
    expect($this->propAdmin->can('delete', $this->cluster))->toBeTrue();
    expect($this->orgAdmin->can('delete', $this->cluster))->toBeTrue();
    expect($this->agencyAdmin->can('delete', $this->cluster))->toBeTrue();
    expect($this->superUser->can('delete', $this->cluster))->toBeTrue();
});

it('forbids an editor from deleting an issue cluster', function (): void {
    expect($this->editor->cannot('delete', $this->cluster))->toBeTrue();
});

it('forbids a viewer from deleting an issue cluster', function (): void {
    expect($this->viewer->cannot('delete', $this->cluster))->toBeTrue();
});
