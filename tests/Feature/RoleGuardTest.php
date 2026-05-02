<?php

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();

    $this->admin = User::factory()
        ->withRole(UserRole::AgencyAdmin, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);

    $this->viewer = User::factory()->create(['agency_id' => $this->agency->id]);
});

// ── SendInvitationController ──────────────────────────────────────────────────

describe('POST invitations.send', function (): void {
    it('forbids viewer-level members from sending invitations', function (): void {
        $this->actingAs($this->viewer)
            ->post(route('invitations.send', $this->agency), [
                'email' => 'new@example.com',
            ])
            ->assertForbidden();
    });

    it('allows an agency admin to send an invitation', function (): void {
        $this->actingAs($this->admin)
            ->post(route('invitations.send', $this->agency), [
                'email' => 'new@example.com',
            ])
            ->assertRedirect();
    });
});

// ── API keys ──────────────────────────────────────────────────────────────────

describe('POST api-keys store', function (): void {
    it('forbids viewer-level members from creating API keys', function (): void {
        $this->actingAs($this->viewer)
            ->post(route('api-keys.store'), [
                'name' => 'My Key',
                'scopes' => ['scans:read'],
            ])
            ->assertForbidden();
    });
});

// ── Notification email routes ─────────────────────────────────────────────────

describe('POST notification-email-routes.store', function (): void {
    it('forbids viewer-level members from creating email notification routes', function (): void {
        $this->actingAs($this->viewer)
            ->post(route('notification-email-routes.store'), [
                'email' => 'alerts@example.com',
            ])
            ->assertForbidden();
    });
});

// ── Notification webhook routes ───────────────────────────────────────────────

describe('POST notification-webhook-routes.store', function (): void {
    it('forbids viewer-level members from creating webhook notification routes', function (): void {
        $this->actingAs($this->viewer)
            ->post(route('notification-webhook-routes.store'), [
                'url' => 'https://hooks.example.com/alert',
            ])
            ->assertForbidden();
    });
});
