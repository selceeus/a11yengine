<?php

use App\Enums\UserRole;
use App\Listeners\LogFailedLogin;
use App\Models\ActivityLog;
use App\Models\Agency;
use App\Models\User;
use App\Notifications\SuspiciousLoginNotification;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->user = User::factory()
        ->withRole(UserRole::AgencyAdmin, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id, 'email' => 'admin@example.com']);

    Cache::flush();
});

it('logs a failed login activity entry', function (): void {
    $event = new Failed('web', null, ['email' => 'admin@example.com', 'password' => 'wrong']);

    (new LogFailedLogin)->handle($event);

    $log = ActivityLog::first();
    expect($log)->not->toBeNull()
        ->and($log->event->value)->toBe('user.login_failed')
        ->and($log->agency_id)->toBe($this->agency->id)
        ->and($log->actor_type)->toBe('unknown');
});

it('does not dispatch notification below threshold', function (): void {
    Notification::fake();

    $event = new Failed('web', null, ['email' => 'admin@example.com', 'password' => 'wrong']);

    // 4 attempts — below threshold of 5
    foreach (range(1, 4) as $i) {
        (new LogFailedLogin)->handle($event);
    }

    Notification::assertNothingSent();
});

it('dispatches SuspiciousLoginNotification at threshold', function (): void {
    Notification::fake();

    $event = new Failed('web', null, ['email' => 'admin@example.com', 'password' => 'wrong']);

    foreach (range(1, 5) as $i) {
        (new LogFailedLogin)->handle($event);
    }

    Notification::assertSentTo($this->user, SuspiciousLoginNotification::class);
});

it('skips unknown email silently', function (): void {
    $event = new Failed('web', null, ['email' => 'nobody@example.com', 'password' => 'wrong']);

    (new LogFailedLogin)->handle($event);

    expect(ActivityLog::count())->toBe(0);
});
