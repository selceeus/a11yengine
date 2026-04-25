<?php

use App\Enums\ActivityLogEvent;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->admin = User::factory()
        ->withRole(UserRole::AgencyAdmin, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);
});

it('renders the activity log page with log entries', function (): void {
    ActivityLog::factory()->count(3)->create(['agency_id' => $this->agency->id]);

    $this->actingAs($this->admin)
        ->get('/settings/activity-log')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/activity-log')
            ->has('logs.data', 3)
            ->has('categories')
            ->has('filters')
        );
});

it('filters log entries by category', function (): void {
    ActivityLog::factory()->create([
        'agency_id' => $this->agency->id,
        'event' => ActivityLogEvent::UserLogin,
    ]);

    ActivityLog::factory()->create([
        'agency_id' => $this->agency->id,
        'event' => ActivityLogEvent::ScanStarted,
    ]);

    $this->actingAs($this->admin)
        ->get('/settings/activity-log?category=authentication')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/activity-log')
            ->has('logs.data', 1)
            ->where('logs.data.0.event_category', 'authentication')
        );
});

it('filters log entries by date range', function (): void {
    ActivityLog::factory()->create([
        'agency_id' => $this->agency->id,
        'created_at' => now()->subDays(10),
    ]);

    ActivityLog::factory()->create([
        'agency_id' => $this->agency->id,
        'created_at' => now()->subDays(30),
    ]);

    $from = now()->subDays(15)->toDateString();
    $to = now()->toDateString();

    $this->actingAs($this->admin)
        ->get("/settings/activity-log?date_from={$from}&date_to={$to}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/activity-log')
            ->has('logs.data', 1)
        );
});

it('paginates log entries', function (): void {
    ActivityLog::factory()->count(60)->create(['agency_id' => $this->agency->id]);

    $this->actingAs($this->admin)
        ->get('/settings/activity-log')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/activity-log')
            ->where('logs.total', 60)
            ->where('logs.per_page', 50)
            ->has('logs.data', 50)
        );
});

it('returns 302 redirect for unauthenticated users', function (): void {
    $this->get('/settings/activity-log')->assertRedirect('/login');
});
