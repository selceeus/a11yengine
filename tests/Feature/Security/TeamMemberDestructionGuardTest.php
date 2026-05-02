<?php

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\AgencyInvitation;
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

describe('DELETE team.members.destroy', function (): void {
    it('forbids a viewer from deleting a team member', function (): void {
        $member = User::factory()->create(['agency_id' => $this->agency->id]);

        $this->actingAs($this->viewer)
            ->delete(route('team.members.destroy', $member))
            ->assertForbidden();
    });

    it('allows an agency admin to delete a team member', function (): void {
        $member = User::factory()->create(['agency_id' => $this->agency->id]);

        $this->actingAs($this->admin)
            ->delete(route('team.members.destroy', $member))
            ->assertRedirect();
    });
});

describe('DELETE team.invitations.destroy', function (): void {
    it('forbids a viewer from cancelling an invitation', function (): void {
        $invitation = AgencyInvitation::factory()->create(['agency_id' => $this->agency->id]);

        $this->actingAs($this->viewer)
            ->delete(route('team.invitations.destroy', $invitation))
            ->assertForbidden();
    });

    it('allows an agency admin to cancel an invitation', function (): void {
        $invitation = AgencyInvitation::factory()->create(['agency_id' => $this->agency->id]);

        $this->actingAs($this->admin)
            ->delete(route('team.invitations.destroy', $invitation))
            ->assertRedirect();
    });
});
