<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Agency;
use App\Models\User;
use App\Models\UserRole;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates agency_admin role for users with an agency_id', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    $this->artisan('app:backfill-user-roles')->assertSuccessful();

    expect(
        UserRole::query()
            ->where('user_id', $user->id)
            ->where('role', UserRoleEnum::AgencyAdmin->value)
            ->where('agency_id', $agency->id)
            ->exists()
    )->toBeTrue();
});

it('skips users without an agency_id', function (): void {
    $user = User::factory()->create(['agency_id' => null]);

    $this->artisan('app:backfill-user-roles')->assertSuccessful();

    expect(UserRole::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('does not create duplicate roles when run twice', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    $this->artisan('app:backfill-user-roles')->assertSuccessful();
    $this->artisan('app:backfill-user-roles')->assertSuccessful();

    expect(
        UserRole::query()
            ->where('user_id', $user->id)
            ->where('role', UserRoleEnum::AgencyAdmin->value)
            ->count()
    )->toBe(1);
});

it('sets created_at and updated_at timestamps on created roles', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    $this->artisan('app:backfill-user-roles')->assertSuccessful();

    $role = UserRole::query()
        ->where('user_id', $user->id)
        ->where('role', UserRoleEnum::AgencyAdmin->value)
        ->first();

    expect($role->created_at)->not->toBeNull()
        ->and($role->updated_at)->not->toBeNull();
});

it('does not write to the database in dry-run mode', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    $this->artisan('app:backfill-user-roles', ['--dry-run' => true])->assertSuccessful();

    expect(UserRole::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('handles multiple users across multiple agencies', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();
    $userA = User::factory()->create(['agency_id' => $agencyA->id]);
    $userB = User::factory()->create(['agency_id' => $agencyB->id]);

    $this->artisan('app:backfill-user-roles')->assertSuccessful();

    expect(
        UserRole::query()->where('user_id', $userA->id)->where('agency_id', $agencyA->id)->exists()
    )->toBeTrue()
        ->and(
            UserRole::query()->where('user_id', $userB->id)->where('agency_id', $agencyB->id)->exists()
        )->toBeTrue();
});
