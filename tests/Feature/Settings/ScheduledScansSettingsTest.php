<?php

use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\ScheduledScan;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create();

    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($this->user);
});

it('returns the scheduled scans settings page', function (): void {
    $this->get(route('scheduled-scans.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/scheduled-scans'));
});

it('passes the agency scheduled scans to the page', function (): void {
    ScheduledScan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->count(3)
        ->create();

    $this->get(route('scheduled-scans.index'))
        ->assertInertia(fn ($page) => $page->has('scheduledScans', 3));
});

it('does not expose scheduled scans from another agency', function (): void {
    ScheduledScan::factory()->count(2)->create(); // different agency

    $this->get(route('scheduled-scans.index'))
        ->assertInertia(fn ($page) => $page->has('scheduledScans', 0));
});

it('includes property and organization data in each scheduled scan entry', function (): void {
    ScheduledScan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();

    $this->get(route('scheduled-scans.index'))
        ->assertInertia(fn ($page) => $page
            ->has('scheduledScans.0.property')
            ->has('scheduledScans.0.organization')
            ->has('scheduledScans.0.is_active')
            ->has('scheduledScans.0.frequency')
        );
});

it('redirects unauthenticated users from the scheduled scans settings page', function (): void {
    $this->post('/logout');

    $this->get(route('scheduled-scans.index'))->assertRedirect(route('login'));
});
