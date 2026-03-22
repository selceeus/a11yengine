<?php

use App\Enums\IssueSeverity;
use App\Events\ScanCompleted;
use App\Listeners\NotifyScanCompleted;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;
use App\Notifications\IssueAssignedNotification;
use App\Notifications\ScanCompletedNotification;
use Illuminate\Support\Facades\Notification;

// ── Helpers ─────────────────────────────────────────────────────────────────

function setupPreferencesTenant(): array
{
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
    ]);
    $user = User::factory()->create(['agency_id' => $agency->id]);
    app()->instance(Agency::class, $agency);

    return [$user, $agency, $organization, $property];
}

// ── NotificationPreference::isEnabled ──────────────────────────────────────

it('defaults to enabled when no preference record exists', function (): void {
    [$user] = setupPreferencesTenant();

    expect(NotificationPreference::isEnabled($user, 'scan_completed', 'mail'))->toBeTrue();
    expect(NotificationPreference::isEnabled($user, 'issue_assigned', 'database'))->toBeTrue();
    expect(NotificationPreference::isEnabled($user, 'weekly_digest', 'mail'))->toBeTrue();
});

it('returns false when preference is explicitly disabled', function (): void {
    [$user, $agency] = setupPreferencesTenant();

    NotificationPreference::create([
        'user_id' => $user->id,
        'agency_id' => $agency->id,
        'notification_type' => 'scan_completed',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    expect(NotificationPreference::isEnabled($user, 'scan_completed', 'mail'))->toBeFalse();
    expect(NotificationPreference::isEnabled($user, 'scan_completed', 'database'))->toBeTrue();
});

it('returns true when preference is explicitly enabled', function (): void {
    [$user, $agency] = setupPreferencesTenant();

    NotificationPreference::create([
        'user_id' => $user->id,
        'agency_id' => $agency->id,
        'notification_type' => 'issue_assigned',
        'channel' => 'database',
        'enabled' => true,
    ]);

    expect(NotificationPreference::isEnabled($user, 'issue_assigned', 'database'))->toBeTrue();
});

// ── Settings Page ──────────────────────────────────────────────────────────

it('renders notification preferences settings page', function (): void {
    [$user] = setupPreferencesTenant();

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('notification-preferences.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/notifications')
            ->has('notificationTypes')
            ->has('channels')
            ->has('preferences'));
});

it('updates notification preferences', function (): void {
    [$user, $agency] = setupPreferencesTenant();

    $this->actingAs($user)
        ->patch(route('notification-preferences.update'), [
            'preferences' => [
                'scan_completed.mail' => false,
                'scan_completed.database' => true,
                'issue_assigned.mail' => true,
                'issue_assigned.database' => false,
                'weekly_digest.mail' => false,
            ],
        ])
        ->assertRedirect(route('notification-preferences.edit'));

    expect(NotificationPreference::where('user_id', $user->id)->count())->toBe(5);

    expect(NotificationPreference::isEnabled($user, 'scan_completed', 'mail'))->toBeFalse();
    expect(NotificationPreference::isEnabled($user, 'scan_completed', 'database'))->toBeTrue();
    expect(NotificationPreference::isEnabled($user, 'issue_assigned', 'database'))->toBeFalse();
    expect(NotificationPreference::isEnabled($user, 'weekly_digest', 'mail'))->toBeFalse();
});

it('rejects unauthenticated access to notification preferences', function (): void {
    $this->get(route('notification-preferences.edit'))
        ->assertRedirect(route('login'));
});

it('can toggle preferences on and off', function (): void {
    [$user] = setupPreferencesTenant();

    // Disable
    $this->actingAs($user)
        ->patch(route('notification-preferences.update'), [
            'preferences' => [
                'scan_completed.mail' => false,
            ],
        ]);

    expect(NotificationPreference::isEnabled($user, 'scan_completed', 'mail'))->toBeFalse();

    // Re-enable
    $this->actingAs($user)
        ->patch(route('notification-preferences.update'), [
            'preferences' => [
                'scan_completed.mail' => true,
            ],
        ]);

    expect(NotificationPreference::isEnabled($user, 'scan_completed', 'mail'))->toBeTrue();
    // Only one record (updateOrCreate)
    expect(NotificationPreference::where('user_id', $user->id)
        ->where('notification_type', 'scan_completed')
        ->where('channel', 'mail')
        ->count())->toBe(1);
});

// ── Preference-Aware Notification Dispatch ─────────────────────────────────

it('respects preferences when sending scan completed notification', function (): void {
    Notification::fake();

    [$user, $agency, $organization, $property] = setupPreferencesTenant();

    // Disable mail channel for scan_completed
    NotificationPreference::create([
        'user_id' => $user->id,
        'agency_id' => $agency->id,
        'notification_type' => 'scan_completed',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'total_violations' => 3,
    ]);

    $listener = new NotifyScanCompleted;
    $listener->handle(new ScanCompleted($scan));

    Notification::assertSentTo($user, ScanCompletedNotification::class, function ($notification) use ($user) {
        $channels = $notification->via($user);

        return in_array('database', $channels) && ! in_array('mail', $channels);
    });
});

it('sends no notification when all channels are disabled', function (): void {
    Notification::fake();

    [$user, $agency, $organization, $property] = setupPreferencesTenant();

    // Disable both channels
    NotificationPreference::create([
        'user_id' => $user->id,
        'agency_id' => $agency->id,
        'notification_type' => 'scan_completed',
        'channel' => 'mail',
        'enabled' => false,
    ]);
    NotificationPreference::create([
        'user_id' => $user->id,
        'agency_id' => $agency->id,
        'notification_type' => 'scan_completed',
        'channel' => 'database',
        'enabled' => false,
    ]);

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $listener = new NotifyScanCompleted;
    $listener->handle(new ScanCompleted($scan));

    // When via() returns empty, Laravel skips recording the notification entirely
    Notification::assertNotSentTo($user, ScanCompletedNotification::class);
});

it('respects preferences for issue assigned notification', function (): void {
    Notification::fake();

    [$user, $agency, $organization, $property] = setupPreferencesTenant();

    $assignee = User::factory()->create(['agency_id' => $agency->id]);

    // Disable database channel for issue_assigned
    NotificationPreference::create([
        'user_id' => $assignee->id,
        'agency_id' => $agency->id,
        'notification_type' => 'issue_assigned',
        'channel' => 'database',
        'enabled' => false,
    ]);

    $issue = Issue::factory()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'severity' => IssueSeverity::Critical,
    ]);

    $this->actingAs($user)
        ->postJson(route('api.issues.assign', $issue), ['user_id' => $assignee->id])
        ->assertOk();

    Notification::assertSentTo($assignee, IssueAssignedNotification::class, function ($notification) use ($assignee) {
        $channels = $notification->via($assignee);

        return in_array('mail', $channels) && ! in_array('database', $channels);
    });
});

it('skips weekly digest when mail preference is disabled', function (): void {
    Notification::fake();

    [$user, $agency] = setupPreferencesTenant();

    NotificationPreference::create([
        'user_id' => $user->id,
        'agency_id' => $agency->id,
        'notification_type' => 'weekly_digest',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    $this->artisan('digest:weekly', ['--agency' => $agency->id])
        ->assertSuccessful();

    Notification::assertNothingSentTo($user);
});
