<?php

use App\Models\Agency;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('only returns organizations scoped to the authenticated users agency', function (): void {
    $agency = Agency::factory()->create();
    $otherAgency = Agency::factory()->create();

    $user = User::factory()->create(['agency_id' => $agency->id]);

    $ownedOrganizations = Organization::factory()->count(3)->create(['agency_id' => $agency->id]);
    Organization::factory()->count(2)->create(['agency_id' => $otherAgency->id]);

    app()->instance(Agency::class, $agency);

    $this->actingAs($user)
        ->get(route('organizations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/index')
            ->has('organizations', 3)
            ->where('organizations.0.agency_id', $agency->id)
        );
});

it('cannot access another agencys organizations', function (): void {
    $agency = Agency::factory()->create();
    $otherAgency = Agency::factory()->create();

    $user = User::factory()->create(['agency_id' => $agency->id]);

    $otherOrganizations = Organization::factory()->count(2)->create(['agency_id' => $otherAgency->id]);

    app()->instance(Agency::class, $agency);

    $this->actingAs($user)
        ->get(route('organizations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/index')
            ->has('organizations', 0)
            ->missing('organizations.0')
        );
});
