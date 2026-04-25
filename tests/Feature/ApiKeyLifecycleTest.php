<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Agency;
use App\Models\ApiKey;
use App\Models\User;
use App\Notifications\ApiKeyExpiringSoonNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->admin = User::factory()
        ->withRole(UserRole::AgencyAdmin, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);
});

it('notifies creator and admins for keys expiring within the window', function (): void {
    Notification::fake();

    $creator = User::factory()->create(['agency_id' => $this->agency->id]);

    ApiKey::factory()->create([
        'agency_id' => $this->agency->id,
        'created_by' => $creator->id,
        'expires_at' => now()->addDays(15),
    ]);

    $this->artisan('api-keys:notify-expiring', ['--days' => 30])->assertSuccessful();

    Notification::assertSentTo($creator, ApiKeyExpiringSoonNotification::class);
    Notification::assertSentTo($this->admin, ApiKeyExpiringSoonNotification::class);
});

it('does not notify for already-revoked expiring keys', function (): void {
    Notification::fake();

    ApiKey::factory()->create([
        'agency_id' => $this->agency->id,
        'created_by' => $this->admin->id,
        'expires_at' => now()->addDays(15),
        'revoked_at' => now()->subDay(),
    ]);

    $this->artisan('api-keys:notify-expiring', ['--days' => 30])->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not notify for keys expiring outside the window', function (): void {
    Notification::fake();

    ApiKey::factory()->create([
        'agency_id' => $this->agency->id,
        'created_by' => $this->admin->id,
        'expires_at' => now()->addDays(60),
    ]);

    $this->artisan('api-keys:notify-expiring', ['--days' => 30])->assertSuccessful();

    Notification::assertNothingSent();
});

it('auto-revokes expired keys and logs the event', function (): void {
    $apiKey = ApiKey::factory()->create([
        'agency_id' => $this->agency->id,
        'created_by' => $this->admin->id,
        'expires_at' => now()->subDay(),
    ]);

    $this->artisan('api-keys:revoke-expired')->assertSuccessful();

    $apiKey->refresh();
    expect($apiKey->revoked_at)->not->toBeNull();

    $log = ActivityLog::where('event', 'api_key.revoked')->first();
    expect($log)->not->toBeNull()
        ->and($log->metadata['reason'])->toBe('auto_revoked_expired');
});

it('does not double-revoke already-revoked expired keys', function (): void {
    $revokedAt = now()->subWeek();

    ApiKey::factory()->create([
        'agency_id' => $this->agency->id,
        'created_by' => $this->admin->id,
        'expires_at' => now()->subDay(),
        'revoked_at' => $revokedAt,
    ]);

    $this->artisan('api-keys:revoke-expired')->assertSuccessful();

    // revoked_at should remain unchanged — no new log entry
    expect(ActivityLog::where('event', 'api_key.revoked')->count())->toBe(0);
});
