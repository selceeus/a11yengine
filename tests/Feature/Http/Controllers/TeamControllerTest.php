<?php

use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($this->user);
});

// ─── index ───────────────────────────────────────────────────────────────────

it('returns the team index page', function (): void {
    $this->get(route('team.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('team/index'));
});

it('passes members belonging to the agency', function (): void {
    User::factory()->create(['agency_id' => $this->agency->id]);

    $this->get(route('team.index'))
        ->assertInertia(fn ($page) => $page->has('members', 2));
});

it('does not expose members from another agency', function (): void {
    User::factory()->create();

    $this->get(route('team.index'))
        ->assertInertia(fn ($page) => $page->has('members', 1));
});

it('passes pending invitations belonging to the agency', function (): void {
    AgencyInvitation::factory()->create(['agency_id' => $this->agency->id]);

    $this->get(route('team.index'))
        ->assertInertia(fn ($page) => $page->has('invitations', 1));
});

it('does not expose invitations from another agency', function (): void {
    AgencyInvitation::factory()->create();

    $this->get(route('team.index'))
        ->assertInertia(fn ($page) => $page->has('invitations', 0));
});

it('does not expose accepted invitations on the index', function (): void {
    AgencyInvitation::factory()->create([
        'agency_id' => $this->agency->id,
        'accepted_at' => now(),
    ]);

    $this->get(route('team.index'))
        ->assertInertia(fn ($page) => $page->has('invitations', 0));
});

it('redirects unauthenticated users from the team index', function (): void {
    $this->post('/logout');

    $this->get(route('team.index'))->assertRedirect(route('login'));
});

// ─── destroyMember ───────────────────────────────────────────────────────────

it('removes a team member', function (): void {
    $member = User::factory()->create(['agency_id' => $this->agency->id]);

    $this->delete(route('team.members.destroy', $member))
        ->assertRedirect(route('team.index'));

    $this->assertDatabaseMissing('users', ['id' => $member->id]);
});

it('cannot remove oneself', function (): void {
    $this->delete(route('team.members.destroy', $this->user))
        ->assertStatus(422);
});

it('cannot remove a member from another agency', function (): void {
    $other = User::factory()->create();

    $this->delete(route('team.members.destroy', $other))
        ->assertForbidden();
});

it('redirects unauthenticated users from destroyMember', function (): void {
    $this->post('/logout');
    $member = User::factory()->create(['agency_id' => $this->agency->id]);

    $this->delete(route('team.members.destroy', $member))->assertRedirect(route('login'));
});

// ─── destroyInvitation ───────────────────────────────────────────────────────

it('cancels a pending invitation', function (): void {
    $invitation = AgencyInvitation::factory()->create(['agency_id' => $this->agency->id]);

    $this->delete(route('team.invitations.destroy', $invitation))
        ->assertRedirect(route('team.index'));

    $this->assertDatabaseMissing('agency_invitations', ['id' => $invitation->id]);
});

it('cannot cancel an invitation from another agency', function (): void {
    $invitation = AgencyInvitation::factory()->create();

    $this->delete(route('team.invitations.destroy', $invitation))
        ->assertForbidden();
});

it('redirects unauthenticated users from destroyInvitation', function (): void {
    $this->post('/logout');
    $invitation = AgencyInvitation::factory()->create(['agency_id' => $this->agency->id]);

    $this->delete(route('team.invitations.destroy', $invitation))->assertRedirect(route('login'));
});
