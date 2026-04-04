<?php

use App\Enums\UserRole as UserRoleEnum;
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

    $this->user = User::factory()->withRole(UserRoleEnum::AgencyAdmin, agencyId: $this->agency->id)->create(['agency_id' => $this->agency->id]);
    $this->actingAs($this->user);
});

// ─── index ───────────────────────────────────────────────────────────────────

it('returns a list of scheduled scans for the agency', function (): void {
    ScheduledScan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->count(2)
        ->create();

    $this->getJson(route('api.scheduled-scans.index'))
        ->assertOk()
        ->assertJsonCount(2, 'scheduledScans');
});

it('does not expose scheduled scans from another agency on index', function (): void {
    ScheduledScan::factory()->count(3)->create(); // belongs to a different agency

    $this->getJson(route('api.scheduled-scans.index'))
        ->assertOk()
        ->assertJsonCount(0, 'scheduledScans');
});

it('includes property and organization relations in the index response', function (): void {
    ScheduledScan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();

    $this->getJson(route('api.scheduled-scans.index'))
        ->assertOk()
        ->assertJsonStructure([
            'scheduledScans' => [
                '*' => ['id', 'is_active', 'frequency', 'next_run_at', 'property', 'organization'],
            ],
        ]);
});

it('redirects unauthenticated users from the scheduled scans index', function (): void {
    $this->post('/logout');

    $this->getJson(route('api.scheduled-scans.index'))->assertUnauthorized();
});

// ─── toggle ───────────────────────────────────────────────────────────────────

it('flips is_active from true to false when toggled', function (): void {
    $schedule = ScheduledScan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create(['is_active' => true]);

    $this->patchJson(route('api.properties.scheduled-scan.toggle', [$this->property, $schedule]))
        ->assertOk()
        ->assertJsonPath('scheduledScan.is_active', false);

    expect($schedule->fresh()->is_active)->toBeFalse();
});

it('flips is_active from false to true when toggled', function (): void {
    $schedule = ScheduledScan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create(['is_active' => false]);

    $this->patchJson(route('api.properties.scheduled-scan.toggle', [$this->property, $schedule]))
        ->assertOk()
        ->assertJsonPath('scheduledScan.is_active', true);

    expect($schedule->fresh()->is_active)->toBeTrue();
});

it('returns the full updated scheduled scan in the toggle response', function (): void {
    $schedule = ScheduledScan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create(['is_active' => true]);

    $this->patchJson(route('api.properties.scheduled-scan.toggle', [$this->property, $schedule]))
        ->assertOk()
        ->assertJsonStructure([
            'scheduledScan' => ['id', 'is_active', 'frequency', 'next_run_at', 'property', 'organization'],
        ]);
});

it('returns 404 when toggling a scheduled scan from another agency', function (): void {
    $otherSchedule = ScheduledScan::factory()->create();

    // Build URL manually since property/scheduledScan belong to a different agency
    // (TenantScope would make $otherSchedule->property null when loaded by the test user)
    $this->patchJson("/api/properties/{$otherSchedule->property_id}/scheduled-scan/{$otherSchedule->id}/toggle")
        ->assertNotFound();
});

it('redirects unauthenticated users from the toggle endpoint', function (): void {
    $schedule = ScheduledScan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();

    $this->post('/logout');

    $this->patchJson(route('api.properties.scheduled-scan.toggle', [$this->property, $schedule]))
        ->assertUnauthorized();
});
