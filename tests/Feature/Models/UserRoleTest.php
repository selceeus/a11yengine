<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Models\UserRole;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('belongs to a user', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    $userRole = UserRole::factory()->create([
        'user_id' => $user->id,
        'role' => UserRoleEnum::Editor,
    ]);

    expect($userRole->user)->toBeInstanceOf(User::class)
        ->and($userRole->user->is($user))->toBeTrue();
});

it('belongs to an agency when scoped', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    $userRole = UserRole::factory()->create([
        'user_id' => $user->id,
        'role' => UserRoleEnum::AgencyAdmin,
        'agency_id' => $agency->id,
    ]);

    expect($userRole->agency)->toBeInstanceOf(Agency::class)
        ->and($userRole->agency->is($agency))->toBeTrue();
});

it('belongs to an organization when scoped', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $user = User::factory()->create(['agency_id' => $agency->id]);

    $userRole = UserRole::factory()->create([
        'user_id' => $user->id,
        'role' => UserRoleEnum::OrgAdmin,
        'organization_id' => $organization->id,
    ]);

    expect($userRole->organization)->toBeInstanceOf(Organization::class)
        ->and($userRole->organization->is($organization))->toBeTrue();
});

it('belongs to a property when scoped', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    $userRole = UserRole::factory()->create([
        'user_id' => $user->id,
        'role' => UserRoleEnum::PropAdmin,
        'property_id' => $property->id,
    ]);

    expect($userRole->property)->toBeInstanceOf(Property::class)
        ->and($userRole->property->is($property))->toBeTrue();
});

it('casts role to UserRole enum', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    $userRole = UserRole::factory()->create([
        'user_id' => $user->id,
        'role' => UserRoleEnum::Viewer,
    ]);

    expect($userRole->role)->toBeInstanceOf(UserRoleEnum::class)
        ->and($userRole->role)->toBe(UserRoleEnum::Viewer);
});

it('user has many roles', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    UserRole::factory()->count(2)->create([
        'user_id' => $user->id,
    ]);

    expect($user->roles)->toHaveCount(2)
        ->each->toBeInstanceOf(UserRole::class);
});

it('hasRole returns true when user has matching role', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    UserRole::factory()->create([
        'user_id' => $user->id,
        'role' => UserRoleEnum::Editor,
    ]);

    expect($user->hasRole(UserRoleEnum::Editor))->toBeTrue();
});

it('hasRole returns false when user does not have matching role', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    UserRole::factory()->create([
        'user_id' => $user->id,
        'role' => UserRoleEnum::Viewer,
    ]);

    expect($user->hasRole(UserRoleEnum::Editor))->toBeFalse();
});

it('hasRole accepts string role value', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    UserRole::factory()->create([
        'user_id' => $user->id,
        'role' => UserRoleEnum::OrgAdmin,
    ]);

    expect($user->hasRole('org_admin'))->toBeTrue();
});

it('hasRole scopes to a specific scope id', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $user = User::factory()->create(['agency_id' => $agency->id]);

    UserRole::factory()->create([
        'user_id' => $user->id,
        'role' => UserRoleEnum::OrgAdmin,
        'organization_id' => $organization->id,
    ]);

    expect($user->hasRole(UserRoleEnum::OrgAdmin, $organization->id))->toBeTrue()
        ->and($user->hasRole(UserRoleEnum::OrgAdmin, $organization->id + 1))->toBeFalse();
});

it('isSuperUser returns true when user has super_user role', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    UserRole::factory()->create([
        'user_id' => $user->id,
        'role' => UserRoleEnum::SuperUser,
    ]);

    expect($user->isSuperUser())->toBeTrue();
});

it('isSuperUser returns false when user does not have super_user role', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    UserRole::factory()->create([
        'user_id' => $user->id,
        'role' => UserRoleEnum::AgencyAdmin,
    ]);

    expect($user->isSuperUser())->toBeFalse();
});
