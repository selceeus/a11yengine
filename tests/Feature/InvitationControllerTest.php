<?php

use App\Models\Agency;
use App\Models\AgencyInvitation;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
});

// ─── show ───────────────────────────────────────────────────────────────────

it('returns 404 for an invalid token', function (): void {
    $this->get(route('invitations.show', 'invalid-token'))
        ->assertNotFound();
});

it('returns 410 for an expired invitation', function (): void {
    $invitation = AgencyInvitation::factory()->create([
        'agency_id' => $this->agency->id,
        'created_at' => now()->subDays(8),
    ]);

    $this->get(route('invitations.show', $invitation->token))
        ->assertStatus(410);
});

it('returns 410 for an already accepted invitation', function (): void {
    $invitation = AgencyInvitation::factory()->accepted()->create([
        'agency_id' => $this->agency->id,
    ]);

    $this->get(route('invitations.show', $invitation->token))
        ->assertStatus(410);
});

it('renders the accept invitation page for a valid token', function (): void {
    $invitation = AgencyInvitation::factory()->create([
        'agency_id' => $this->agency->id,
        'email' => 'invitee@example.com',
    ]);

    $this->get(route('invitations.show', $invitation->token))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('auth/accept-invitation')
                ->where('email', 'invitee@example.com')
                ->where('token', $invitation->token)
        );
});

// ─── accept ─────────────────────────────────────────────────────────────────

it('creates a user with the correct agency when accepting', function (): void {
    $invitation = AgencyInvitation::factory()->create([
        'agency_id' => $this->agency->id,
        'email' => 'invitee@example.com',
    ]);

    $this->post(route('invitations.accept', $invitation->token), [
        'name' => 'Invited User',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'invitee@example.com',
        'agency_id' => $this->agency->id,
    ]);
});

it('marks the invitation as accepted', function (): void {
    $invitation = AgencyInvitation::factory()->create([
        'agency_id' => $this->agency->id,
        'email' => 'invitee@example.com',
    ]);

    $this->post(route('invitations.accept', $invitation->token), [
        'name' => 'Invited User',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('logs the new user in after accepting', function (): void {
    $invitation = AgencyInvitation::factory()->create([
        'agency_id' => $this->agency->id,
        'email' => 'invitee@example.com',
    ]);

    $this->post(route('invitations.accept', $invitation->token), [
        'name' => 'Invited User',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertRedirectToRoute('dashboard');

    $this->assertAuthenticated();
});

it('redirects to dashboard after accepting', function (): void {
    $invitation = AgencyInvitation::factory()->create([
        'agency_id' => $this->agency->id,
    ]);

    $this->post(route('invitations.accept', $invitation->token), [
        'name' => 'Invited User',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertRedirectToRoute('dashboard');
});

it('validates required fields when accepting', function (): void {
    $invitation = AgencyInvitation::factory()->create([
        'agency_id' => $this->agency->id,
    ]);

    $this->post(route('invitations.accept', $invitation->token), [])
        ->assertSessionHasErrors(['name', 'password']);
});
