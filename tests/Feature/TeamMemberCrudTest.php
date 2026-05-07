<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Agency;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->admin = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->admin->roles()->create([
        'role' => UserRoleEnum::AgencyAdmin,
        'agency_id' => $this->agency->id,
    ]);
});

// ─── Authorization ───────────────────────────────────────────────────────────

it('redirects unauthenticated users from create page', function (): void {
    $this->get(route('team.members.create'))
        ->assertRedirect(route('login'));
});

it('forbids non-admin members from creating users', function (): void {
    $viewer = User::factory()->create(['agency_id' => $this->agency->id]);

    $this->actingAs($viewer)
        ->get(route('team.members.create'))
        ->assertForbidden();
});

it('forbids non-admin members from storing users', function (): void {
    $viewer = User::factory()->create(['agency_id' => $this->agency->id]);

    $this->actingAs($viewer)
        ->post(route('team.members.store'), [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])
        ->assertForbidden();
});

it('forbids editing a member from another agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherUser = User::factory()->create(['agency_id' => $otherAgency->id]);

    $this->actingAs($this->admin)
        ->get(route('team.members.edit', $otherUser))
        ->assertForbidden();
});

// ─── Create ──────────────────────────────────────────────────────────────────

it('renders the create team member page for an agency admin', function (): void {
    $this->actingAs($this->admin)
        ->get(route('team.members.create'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('team/create')
        );
});

it('creates a user directly without an invitation', function (): void {
    $this->actingAs($this->admin)
        ->post(route('team.members.store'), [
            'name' => 'Direct User',
            'email' => 'direct@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])
        ->assertRedirectToRoute('team.index');

    $this->assertDatabaseHas('users', [
        'email' => 'direct@example.com',
        'name' => 'Direct User',
        'agency_id' => $this->agency->id,
    ]);
});

it('sets must_change_password to true on direct creation', function (): void {
    $this->actingAs($this->admin)
        ->post(route('team.members.store'), [
            'name' => 'Direct User',
            'email' => 'direct@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

    $user = User::where('email', 'direct@example.com')->first();
    expect($user->must_change_password)->toBeTrue();
});

it('assigns a role during creation when provided', function (): void {
    $this->actingAs($this->admin)
        ->post(route('team.members.store'), [
            'name' => 'Editor User',
            'email' => 'editor@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role' => UserRoleEnum::Editor->value,
        ]);

    $user = User::where('email', 'editor@example.com')->first();
    expect($user)->not->toBeNull();

    $this->assertDatabaseHas('user_roles', [
        'user_id' => $user->id,
        'role' => UserRoleEnum::Editor->value,
        'agency_id' => $this->agency->id,
    ]);
});

it('validates required fields when creating a team member', function (): void {
    $this->actingAs($this->admin)
        ->post(route('team.members.store'), [])
        ->assertSessionHasErrors(['name', 'email', 'password']);
});

it('prevents duplicate email in the same agency', function (): void {
    $existing = User::factory()->create(['agency_id' => $this->agency->id, 'email' => 'dup@example.com']);

    $this->actingAs($this->admin)
        ->post(route('team.members.store'), [
            'name' => 'Dup',
            'email' => 'dup@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])
        ->assertSessionHasErrors(['email']);
});

// ─── Edit / Update ───────────────────────────────────────────────────────────

it('renders the edit page for an agency member', function (): void {
    $member = User::factory()->create(['agency_id' => $this->agency->id]);

    $this->actingAs($this->admin)
        ->get(route('team.members.edit', $member))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('team/edit')
                ->where('member.id', $member->id)
        );
});

it('updates a member name and email', function (): void {
    $member = User::factory()->create(['agency_id' => $this->agency->id]);

    $this->actingAs($this->admin)
        ->patch(route('team.members.update', $member), [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ])
        ->assertRedirectToRoute('team.members.edit', $member);

    $this->assertDatabaseHas('users', [
        'id' => $member->id,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);
});

// ─── Password Reset ──────────────────────────────────────────────────────────

it('resets a member password and sets must_change_password', function (): void {
    $member = User::factory()->create(['agency_id' => $this->agency->id, 'must_change_password' => false]);

    $this->actingAs($this->admin)
        ->patch(route('team.members.password', $member), [
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
        ->assertRedirectToRoute('team.members.edit', $member);

    $fresh = $member->fresh();
    expect($fresh->must_change_password)->toBeTrue();
    expect($fresh->password)->not->toBe('NewPassword1!');
    expect(Hash::check('NewPassword1!', $fresh->password))->toBeTrue();
});

// ─── Role ────────────────────────────────────────────────────────────────────

it('assigns an agency role to a member', function (): void {
    $member = User::factory()->create(['agency_id' => $this->agency->id]);

    $this->actingAs($this->admin)
        ->patch(route('team.members.role', $member), [
            'role' => UserRoleEnum::Editor->value,
        ])
        ->assertRedirectToRoute('team.members.edit', $member);

    $this->assertDatabaseHas('user_roles', [
        'user_id' => $member->id,
        'role' => UserRoleEnum::Editor->value,
        'agency_id' => $this->agency->id,
    ]);
});

it('removes a role when null is submitted', function (): void {
    $member = User::factory()->create(['agency_id' => $this->agency->id]);
    $member->roles()->create(['role' => UserRoleEnum::Editor, 'agency_id' => $this->agency->id]);

    $this->actingAs($this->admin)
        ->patch(route('team.members.role', $member), ['role' => null])
        ->assertRedirectToRoute('team.members.edit', $member);

    $this->assertDatabaseMissing('user_roles', [
        'user_id' => $member->id,
        'agency_id' => $this->agency->id,
    ]);
});

it('replaces existing agency role when a new one is assigned', function (): void {
    $member = User::factory()->create(['agency_id' => $this->agency->id]);
    $member->roles()->create(['role' => UserRoleEnum::Editor, 'agency_id' => $this->agency->id]);

    $this->actingAs($this->admin)
        ->patch(route('team.members.role', $member), ['role' => UserRoleEnum::Viewer->value])
        ->assertRedirectToRoute('team.members.edit', $member);

    expect(
        UserRole::where('user_id', $member->id)
            ->where('agency_id', $this->agency->id)
            ->count()
    )->toBe(1);

    $this->assertDatabaseHas('user_roles', [
        'user_id' => $member->id,
        'role' => UserRoleEnum::Viewer->value,
    ]);
});

// ─── must_change_password enforcement ───────────────────────────────────────

it('redirects a user with must_change_password set to the password settings page', function (): void {
    $user = User::factory()->create([
        'agency_id' => $this->agency->id,
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirectToRoute('user-password.edit');
});

it('allows access to the password page when must_change_password is set', function (): void {
    $user = User::factory()->create([
        'agency_id' => $this->agency->id,
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->get(route('user-password.edit'))
        ->assertOk();
});

it('clears must_change_password after a successful password update', function (): void {
    $user = User::factory()->create([
        'agency_id' => $this->agency->id,
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
        ->assertRedirect();

    expect($user->fresh()->must_change_password)->toBeFalse();
});
