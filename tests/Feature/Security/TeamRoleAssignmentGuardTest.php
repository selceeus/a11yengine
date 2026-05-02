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

    $this->member = User::factory()
        ->withRole(UserRole::Editor, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);
});

describe('PATCH team.members.role', function (): void {
    it('rejects assigning the SuperUser role', function (): void {
        $this->actingAs($this->admin)
            ->patch(route('team.members.role', $this->member), [
                'role' => UserRole::SuperUser->value,
            ])
            ->assertSessionHasErrors('role');
    });

    it('accepts assigning a valid role', function (): void {
        $this->actingAs($this->admin)
            ->patch(route('team.members.role', $this->member), [
                'role' => UserRole::Viewer->value,
            ])
            ->assertRedirect();
    });
});
