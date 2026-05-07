<?php

use App\Models\Agency;
use App\Models\AgencyInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
});

/**
 * Helper: create an invitation with a known plaintext token and return both.
 *
 * @param  array<string, mixed>  $overrides
 * @return array{0: AgencyInvitation, 1: string}
 */
function makeInvitationWithToken(array $overrides = []): array
{
    $token = Str::random(64);
    $invitation = AgencyInvitation::factory()->create(array_merge(
        ['token_hash' => hash('sha256', $token)],
        $overrides,
    ));

    return [$invitation, $token];
}

// ─── show ───────────────────────────────────────────────────────────────────

it('returns 404 for an invalid token', function (): void {
    $this->get(route('invitations.show', 'invalid-token'))
        ->assertNotFound();
});

it('returns 410 for an expired invitation', function (): void {
    [$invitation, $token] = makeInvitationWithToken([
        'agency_id' => $this->agency->id,
        'created_at' => now()->subDays(8),
    ]);

    $this->get(route('invitations.show', $token))
        ->assertStatus(410);
});

it('returns 410 for an already accepted invitation', function (): void {
    [$invitation, $token] = makeInvitationWithToken([
        'agency_id' => $this->agency->id,
        'accepted_at' => now(),
    ]);

    $this->get(route('invitations.show', $token))
        ->assertStatus(410);
});

it('renders the accept invitation page for a valid token', function (): void {
    [$invitation, $token] = makeInvitationWithToken([
        'agency_id' => $this->agency->id,
        'email' => 'invitee@example.com',
    ]);

    $this->get(route('invitations.show', $token))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('auth/accept-invitation')
                ->where('email', 'invitee@example.com')
                ->where('token', $token)
        );
});

// ─── accept ─────────────────────────────────────────────────────────────────

it('creates a user with the correct agency when accepting', function (): void {
    [$invitation, $token] = makeInvitationWithToken([
        'agency_id' => $this->agency->id,
        'email' => 'invitee@example.com',
    ]);

    $this->post(route('invitations.accept', $token), [
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
    [$invitation, $token] = makeInvitationWithToken([
        'agency_id' => $this->agency->id,
        'email' => 'invitee@example.com',
    ]);

    $this->post(route('invitations.accept', $token), [
        'name' => 'Invited User',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('logs the new user in after accepting', function (): void {
    [$invitation, $token] = makeInvitationWithToken([
        'agency_id' => $this->agency->id,
        'email' => 'invitee@example.com',
    ]);

    $this->post(route('invitations.accept', $token), [
        'name' => 'Invited User',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertRedirectToRoute('dashboard');

    $this->assertAuthenticated();
});

it('redirects to dashboard after accepting', function (): void {
    [$invitation, $token] = makeInvitationWithToken([
        'agency_id' => $this->agency->id,
    ]);

    $this->post(route('invitations.accept', $token), [
        'name' => 'Invited User',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertRedirectToRoute('dashboard');
});

it('validates required fields when accepting', function (): void {
    [$invitation, $token] = makeInvitationWithToken([
        'agency_id' => $this->agency->id,
    ]);

    $this->post(route('invitations.accept', $token), [])
        ->assertSessionHasErrors(['name', 'password']);
});

it('stores only the token hash, not the plaintext token', function (): void {
    $plainToken = Str::random(64);
    $invitation = AgencyInvitation::factory()->create([
        'agency_id' => $this->agency->id,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $stored = DB::table('agency_invitations')
        ->where('id', $invitation->id)
        ->first();

    expect($stored->token_hash)->toBe(hash('sha256', $plainToken));
    expect($stored->token_hash)->not->toBe($plainToken);
});
