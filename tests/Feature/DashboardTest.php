<?php

use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('defaultPropertyId is null when no completed scans exist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('defaultPropertyId', null));
});

test('defaultPropertyId is the property_id of the most recently completed scan', function () {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();

    Scan::factory()
        ->for($agency)
        ->for($organization)
        ->for($property)
        ->completed()
        ->create(['completed_at' => now()]);

    $user = User::factory()->create(['agency_id' => $agency->id]);
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('defaultPropertyId', $property->id));
});
