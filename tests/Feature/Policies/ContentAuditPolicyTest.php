<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Agency;
use App\Models\ContentAudit;
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

    $this->contentAudit = ContentAudit::factory()->create([
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

it('allows any user to viewAny content audits', function (): void {
    expect($this->viewer->can('viewAny', ContentAudit::class))->toBeTrue();
    expect($this->otherAgencyUser->can('viewAny', ContentAudit::class))->toBeTrue();
});

// ─── view ─────────────────────────────────────────────────────────────────────

it('allows same-agency user to view a content audit', function (): void {
    expect($this->viewer->can('view', $this->contentAudit))->toBeTrue();
    expect($this->editor->can('view', $this->contentAudit))->toBeTrue();
});

it('forbids other-agency user from viewing a content audit', function (): void {
    expect($this->otherAgencyUser->cannot('view', $this->contentAudit))->toBeTrue();
});

// ─── create ──────────────────────────────────────────────────────────────────

it('allows editor or higher to create a content audit for a property', function (): void {
    expect($this->editor->can('create', [ContentAudit::class, $this->property]))->toBeTrue();
    expect($this->propAdmin->can('create', [ContentAudit::class, $this->property]))->toBeTrue();
    expect($this->orgAdmin->can('create', [ContentAudit::class, $this->property]))->toBeTrue();
    expect($this->agencyAdmin->can('create', [ContentAudit::class, $this->property]))->toBeTrue();
    expect($this->superUser->can('create', [ContentAudit::class, $this->property]))->toBeTrue();
});

it('forbids a viewer from creating a content audit', function (): void {
    expect($this->viewer->cannot('create', [ContentAudit::class, $this->property]))->toBeTrue();
});

// ─── delete ──────────────────────────────────────────────────────────────────

it('allows propAdmin or higher to delete a content audit', function (): void {
    expect($this->propAdmin->can('delete', $this->contentAudit))->toBeTrue();
    expect($this->orgAdmin->can('delete', $this->contentAudit))->toBeTrue();
    expect($this->agencyAdmin->can('delete', $this->contentAudit))->toBeTrue();
    expect($this->superUser->can('delete', $this->contentAudit))->toBeTrue();
});

it('forbids an editor from deleting a content audit', function (): void {
    expect($this->editor->cannot('delete', $this->contentAudit))->toBeTrue();
});

it('forbids a viewer from deleting a content audit', function (): void {
    expect($this->viewer->cannot('delete', $this->contentAudit))->toBeTrue();
});
