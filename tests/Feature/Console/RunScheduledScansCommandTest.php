<?php

use App\Jobs\RunScanJob;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\ScheduledScan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);
});

it('does nothing when no scans are due', function (): void {
    ScheduledScan::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'next_run_at' => now()->addHour(),
    ]);

    $this->artisan('scans:run-scheduled')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('dispatches RunScanJob for each due schedule', function (): void {
    ScheduledScan::factory()->due()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    $this->artisan('scans:run-scheduled')->assertSuccessful();

    Queue::assertPushed(RunScanJob::class, 1);
});

it('deactivates a one-time schedule after running', function (): void {
    $schedule = ScheduledScan::factory()->once()->due()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    $this->artisan('scans:run-scheduled')->assertSuccessful();

    expect($schedule->fresh()->is_active)->toBeFalse()
        ->and($schedule->fresh()->last_run_at)->not->toBeNull();
});

it('updates next_run_at for a recurring schedule', function (): void {
    Carbon::setTestNow('2025-06-01 10:00:00');

    $schedule = ScheduledScan::factory()->due()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'type' => 'recurring',
        'frequency' => 'weekly',
    ]);

    $this->artisan('scans:run-scheduled')->assertSuccessful();

    $refreshed = $schedule->fresh();

    expect($refreshed->is_active)->toBeTrue()
        ->and($refreshed->last_run_at->toDateString())->toBe('2025-06-01')
        ->and($refreshed->next_run_at->toDateString())->toBe('2025-06-08');

    Carbon::setTestNow();
});

it('does not dispatch for inactive schedules', function (): void {
    ScheduledScan::factory()->due()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'is_active' => false,
    ]);

    $this->artisan('scans:run-scheduled')->assertSuccessful();

    Queue::assertNothingPushed();
});
