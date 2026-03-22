<?php

use App\Enums\ScanStatus;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Support\Carbon;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);
});

it('marks a scan as failed when it has been running for more than 20 minutes', function (): void {
    $scan = Scan::withoutGlobalScopes()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => ScanStatus::Running,
        'started_at' => Carbon::now()->subMinutes(25),
    ]);

    $this->artisan('scans:expire-stuck')->assertSuccessful();

    expect($scan->fresh()->status)->toBe(ScanStatus::Failed)
        ->and($scan->fresh()->error_message)->toBe('Scan timed out after 20 minutes.');
});

it('does not fail a scan that has been running for less than 20 minutes', function (): void {
    $scan = Scan::withoutGlobalScopes()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => ScanStatus::Running,
        'started_at' => Carbon::now()->subMinutes(10),
    ]);

    $this->artisan('scans:expire-stuck')->assertSuccessful();

    expect($scan->fresh()->status)->toBe(ScanStatus::Running);
});

it('does not affect scans that are already completed or failed', function (): void {
    $completed = Scan::withoutGlobalScopes()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => ScanStatus::Completed,
        'started_at' => Carbon::now()->subHours(2),
    ]);

    $failed = Scan::withoutGlobalScopes()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => ScanStatus::Failed,
        'started_at' => Carbon::now()->subHours(2),
    ]);

    $this->artisan('scans:expire-stuck')->assertSuccessful();

    expect($completed->fresh()->status)->toBe(ScanStatus::Completed)
        ->and($failed->fresh()->status)->toBe(ScanStatus::Failed);
});

it('exits with success when there are no stuck scans', function (): void {
    $this->artisan('scans:expire-stuck')
        ->assertSuccessful()
        ->expectsOutput('No stuck scans found.');
});
