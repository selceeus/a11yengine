<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
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
    $this->scan = Scan::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);
});

// ─── viewAny ─────────────────────────────────────────────────────────────────

it('allows any authenticated user to view the scan list', function (): void {
    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    expect($user->can('viewAny', Scan::class))->toBeTrue();
});

// ─── create ──────────────────────────────────────────────────────────────────

it('allows an editor to create a scan for their property', function (): void {
    $user = User::factory()->withRole(UserRoleEnum::Editor, propertyId: $this->property->id)->create(['agency_id' => $this->agency->id]);
    expect($user->can('create', [Scan::class, $this->property]))->toBeTrue();
});

it('denies a viewer from creating a scan', function (): void {
    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    expect($user->can('create', [Scan::class, $this->property]))->toBeFalse();
});

// ─── view ────────────────────────────────────────────────────────────────────

it('allows a user to view a scan belonging to their agency', function (): void {
    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    expect($user->can('view', $this->scan))->toBeTrue();
});

it('denies a user from viewing a scan belonging to another agency', function (): void {
    $user = User::factory()->create(['agency_id' => $this->otherAgency->id]);
    expect($user->can('view', $this->scan))->toBeFalse();
});

// ─── delete ──────────────────────────────────────────────────────────────────

it('allows a property admin to delete a scan belonging to their property', function (): void {
    $user = User::factory()->withRole(UserRoleEnum::PropAdmin, propertyId: $this->property->id)->create(['agency_id' => $this->agency->id]);
    expect($user->can('delete', $this->scan))->toBeTrue();
});

it('denies a user from deleting a scan belonging to another agency', function (): void {
    $otherProperty = Property::factory()->create(['agency_id' => $this->otherAgency->id]);
    $user = User::factory()->withRole(UserRoleEnum::PropAdmin, propertyId: $otherProperty->id)->create(['agency_id' => $this->otherAgency->id]);
    expect($user->can('delete', $this->scan))->toBeFalse();
});
