<?php

use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\ScheduledScan;
use App\Models\User;
use Illuminate\Support\Carbon;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);
    $this->actor = User::factory()->create(['agency_id' => $this->agency->id]);
});

// ── Authentication ─────────────────────────────────────────────────────────────

it('rejects unauthenticated store', function (): void {
    $this->postJson(route('api.properties.scheduled-scan.store', $this->property))
        ->assertUnauthorized();
});

// ── store – recurring ──────────────────────────────────────────────────────────

it('creates a recurring scheduled scan', function (): void {
    Carbon::setTestNow('2025-01-01 12:00:00');

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.scheduled-scan.store', $this->property), [
            'type' => 'recurring',
            'frequency' => 'weekly',
        ])
        ->assertOk()
        ->assertJsonPath('scheduledScan.type', 'recurring')
        ->assertJsonPath('scheduledScan.frequency', 'weekly');

    $this->assertDatabaseHas('scheduled_scans', [
        'property_id' => $this->property->id,
        'type' => 'recurring',
        'frequency' => 'weekly',
        'is_active' => true,
    ]);

    Carbon::setTestNow();
});

it('creates a one-time scheduled scan', function (): void {
    $scheduledAt = now()->addDay()->format('Y-m-d\TH:i');

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.scheduled-scan.store', $this->property), [
            'type' => 'once',
            'scheduled_at' => $scheduledAt,
        ])
        ->assertOk()
        ->assertJsonPath('scheduledScan.type', 'once')
        ->assertJsonPath('scheduledScan.frequency', null);

    $this->assertDatabaseHas('scheduled_scans', [
        'property_id' => $this->property->id,
        'type' => 'once',
        'frequency' => null,
        'is_active' => true,
    ]);
});

it('replaces an existing active schedule on store (upsert)', function (): void {
    ScheduledScan::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'type' => 'recurring',
        'frequency' => 'daily',
    ]);

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.scheduled-scan.store', $this->property), [
            'type' => 'recurring',
            'frequency' => 'monthly',
        ])
        ->assertOk();

    expect(ScheduledScan::withoutGlobalScopes()->where('property_id', $this->property->id)->where('is_active', true)->count())->toBe(1)
        ->and(ScheduledScan::withoutGlobalScopes()->where('property_id', $this->property->id)->where('is_active', true)->sole()->frequency)->toBe('monthly');
});

// ── store – validation ─────────────────────────────────────────────────────────

it('rejects an invalid frequency', function (): void {
    $this->actingAs($this->actor)
        ->postJson(route('api.properties.scheduled-scan.store', $this->property), [
            'type' => 'recurring',
            'frequency' => 'hourly',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['frequency']);
});

it('requires scheduled_at for a one-time scan', function (): void {
    $this->actingAs($this->actor)
        ->postJson(route('api.properties.scheduled-scan.store', $this->property), [
            'type' => 'once',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['scheduled_at']);
});

it('rejects a scheduled_at in the past', function (): void {
    $this->actingAs($this->actor)
        ->postJson(route('api.properties.scheduled-scan.store', $this->property), [
            'type' => 'once',
            'scheduled_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['scheduled_at']);
});

// ── update ─────────────────────────────────────────────────────────────────────

it('updates an existing scheduled scan', function (): void {
    $schedule = ScheduledScan::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'type' => 'recurring',
        'frequency' => 'daily',
    ]);

    $this->actingAs($this->actor)
        ->putJson(route('api.properties.scheduled-scan.update', [$this->property, $schedule]), [
            'type' => 'recurring',
            'frequency' => 'monthly',
        ])
        ->assertOk()
        ->assertJsonPath('scheduledScan.frequency', 'monthly');

    expect($schedule->fresh()->frequency)->toBe('monthly');
});

// ── cross-agency isolation ─────────────────────────────────────────────────────

it('returns 404 when property belongs to a different agency', function (): void {
    $otherProperty = Property::factory()->create();

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.scheduled-scan.store', $otherProperty), [
            'type' => 'recurring',
            'frequency' => 'weekly',
        ])
        ->assertNotFound();
});

// ── destroy ────────────────────────────────────────────────────────────────────

it('rejects unauthenticated destroy', function (): void {
    $schedule = ScheduledScan::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    $this->deleteJson(route('api.properties.scheduled-scan.destroy', [$this->property, $schedule]))
        ->assertUnauthorized();
});

it('deactivates a schedule on destroy', function (): void {
    $schedule = ScheduledScan::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    $this->actingAs($this->actor)
        ->deleteJson(route('api.properties.scheduled-scan.destroy', [$this->property, $schedule]))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($schedule->fresh()->is_active)->toBeFalse();
});

// ── timing fields ─────────────────────────────────────────────────────────────

it('stores run_time and run_day_of_week for weekly schedules', function (): void {
    $this->actingAs($this->actor)
        ->postJson(route('api.properties.scheduled-scan.store', $this->property), [
            'type' => 'recurring',
            'frequency' => 'weekly',
            'run_time' => '14:30',
            'run_day_of_week' => 3,
        ])
        ->assertOk()
        ->assertJsonPath('scheduledScan.run_time', '14:30')
        ->assertJsonPath('scheduledScan.run_day_of_week', 3);

    $this->assertDatabaseHas('scheduled_scans', [
        'property_id' => $this->property->id,
        'run_time' => '14:30',
        'run_day_of_week' => 3,
    ]);
});

it('stores run_time and run_day_of_month for monthly schedules', function (): void {
    $this->actingAs($this->actor)
        ->postJson(route('api.properties.scheduled-scan.store', $this->property), [
            'type' => 'recurring',
            'frequency' => 'monthly',
            'run_time' => '06:00',
            'run_day_of_month' => 15,
        ])
        ->assertOk()
        ->assertJsonPath('scheduledScan.run_time', '06:00')
        ->assertJsonPath('scheduledScan.run_day_of_month', 15);
});

it('rejects an invalid run_time format', function (): void {
    $this->actingAs($this->actor)
        ->postJson(route('api.properties.scheduled-scan.store', $this->property), [
            'type' => 'recurring',
            'frequency' => 'daily',
            'run_time' => '25:00',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['run_time']);
});

it('rejects a run_day_of_month out of range', function (): void {
    $this->actingAs($this->actor)
        ->postJson(route('api.properties.scheduled-scan.store', $this->property), [
            'type' => 'recurring',
            'frequency' => 'monthly',
            'run_day_of_month' => 29,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['run_day_of_month']);
});

it('stores next_run_at in the correct timezone for a recurring scan', function (): void {
    // UTC 14:00 on 2026-03-22 = 09:00 CDT (America/Chicago, UTC-5 in March)
    Carbon::setTestNow('2026-03-22 14:00:00');

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.scheduled-scan.store', $this->property), [
            'type' => 'recurring',
            'frequency' => 'daily',
            'run_time' => '09:00',
            'timezone' => 'America/Chicago',
        ])
        ->assertOk();

    $scan = ScheduledScan::withoutGlobalScopes()
        ->where('property_id', $this->property->id)
        ->first();

    // 09:00 CDT today == 14:00 UTC == now, so next run is tomorrow 09:00 CDT = 14:00 UTC
    expect($scan->next_run_at->toDateString())->toBe('2026-03-23')
        ->and($scan->next_run_at->setTimezone('America/Chicago')->format('H:i'))->toBe('09:00');

    Carbon::setTestNow();
});
