<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->admin = User::factory()
        ->withRole(UserRole::AgencyAdmin, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);
});

it('deletes log entries older than the retention window', function (): void {
    // 3 old entries
    ActivityLog::factory()->count(3)->create([
        'agency_id' => $this->agency->id,
        'created_at' => now()->subMonths(25),
    ]);

    // 2 recent entries
    ActivityLog::factory()->count(2)->create([
        'agency_id' => $this->agency->id,
        'created_at' => now()->subMonth(),
    ]);

    $this->artisan('activity-log:prune', ['--months' => 24])->assertSuccessful();

    // Only the 2 recent ones remain, plus 1 prune log entry added by the command
    expect(ActivityLog::withoutGlobalScopes()->where('event', '!=', 'activity_log.pruned')->count())->toBe(2);
});

it('logs a prune event after deleting old entries', function (): void {
    ActivityLog::factory()->count(2)->create([
        'agency_id' => $this->agency->id,
        'created_at' => now()->subMonths(25),
    ]);

    $this->artisan('activity-log:prune', ['--months' => 24])->assertSuccessful();

    $pruneLog = ActivityLog::withoutGlobalScopes()
        ->where('event', 'activity_log.pruned')
        ->first();

    expect($pruneLog)->not->toBeNull()
        ->and($pruneLog->metadata['deleted_count'])->toBe(2)
        ->and($pruneLog->metadata['retention_months'])->toBe(24);
});

it('respects the --months override option', function (): void {
    // 1 entry that is 6 weeks old (within 24 months, but outside 1 month)
    ActivityLog::factory()->create([
        'agency_id' => $this->agency->id,
        'created_at' => now()->subWeeks(6),
    ]);

    // 1 recent entry
    ActivityLog::factory()->create([
        'agency_id' => $this->agency->id,
        'created_at' => now()->subDays(3),
    ]);

    $this->artisan('activity-log:prune', ['--months' => 1])->assertSuccessful();

    expect(ActivityLog::withoutGlobalScopes()->where('event', '!=', 'activity_log.pruned')->count())->toBe(1);
});

it('does nothing when no old log entries exist', function (): void {
    ActivityLog::factory()->count(2)->create([
        'agency_id' => $this->agency->id,
        'created_at' => now()->subMonth(),
    ]);

    $this->artisan('activity-log:prune', ['--months' => 24])->assertSuccessful();

    expect(ActivityLog::withoutGlobalScopes()->count())->toBe(2);
});
