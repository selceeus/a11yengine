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
