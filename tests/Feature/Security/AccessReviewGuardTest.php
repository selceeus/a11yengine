<?php

use App\Enums\UserRole;
use App\Models\AccessReview;
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

    $this->review = AccessReview::factory()->create(['agency_id' => $this->agency->id]);

    $this->member = User::factory()->create(['agency_id' => $this->agency->id]);
});

describe('POST access-reviews.confirm', function (): void {
    it('forbids a viewer from confirming access', function (): void {
        $this->actingAs($this->viewer)
            ->post(route('access-reviews.confirm', [$this->review, $this->member]))
            ->assertForbidden();
    });
});

describe('POST access-reviews.revoke', function (): void {
    it('forbids a viewer from revoking access', function (): void {
        $this->actingAs($this->viewer)
            ->post(route('access-reviews.revoke', [$this->review, $this->member]))
            ->assertForbidden();
    });
});

describe('POST access-reviews.complete', function (): void {
    it('forbids a viewer from completing an access review', function (): void {
        $this->actingAs($this->viewer)
            ->post(route('access-reviews.complete', $this->review))
            ->assertForbidden();
    });

    it('allows an agency admin to complete an access review', function (): void {
        $this->actingAs($this->admin)
            ->post(route('access-reviews.complete', $this->review))
            ->assertRedirect();
    });
});
