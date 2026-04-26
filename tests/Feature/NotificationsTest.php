<?php

use App\Enums\IssueSeverity;
use App\Enums\UserRole;
use App\Events\ScanCompleted;
use App\Listeners\NotifyScanCompleted;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;
use App\Notifications\IssueAssignedNotification;
use App\Notifications\ScanCompletedNotification;
use App\Notifications\WeeklyDigestNotification;
use Illuminate\Support\Facades\Notification;

// ── Helpers ─────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    config(['queue.default' => 'sync']);
});

function setupTenant(): array
{
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
    ]);
    $user = User::factory()
        ->withRole(UserRole::Editor, $agency->id)
        ->create(['agency_id' => $agency->id]);
    app()->instance(Agency::class, $agency);

    return [$user, $agency, $organization, $property];
}

// ── ScanCompletedNotification ───────────────────────────────────────────────

it('sends scan completed notification via listener', function (): void {
    Notification::fake();

    [$user, $agency, $organization, $property] = setupTenant();

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'total_violations' => 5,
    ]);

    $listener = new NotifyScanCompleted;
    $listener->handle(new ScanCompleted($scan));

    Notification::assertSentTo($user, ScanCompletedNotification::class, function ($notification) use ($scan, $user) {
        $data = $notification->toArray($user);

        return $data['scan_id'] === $scan->id
            && $data['total_violations'] === 5;
    });
});

it('sends scan completed notification to all agency users', function (): void {
    Notification::fake();

    [$user1, $agency, $organization, $property] = setupTenant();
    $user2 = User::factory()->create(['agency_id' => $agency->id]);

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $listener = new NotifyScanCompleted;
    $listener->handle(new ScanCompleted($scan));

    Notification::assertSentTo($user1, ScanCompletedNotification::class);
    Notification::assertSentTo($user2, ScanCompletedNotification::class);
});

it('includes correct data in scan completed notification', function (): void {
    [$user, $agency, $organization, $property] = setupTenant();

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'total_violations' => 12,
        'pages_scanned' => 3,
    ]);

    $notification = new ScanCompletedNotification($scan);
    $data = $notification->toArray($user);

    expect($data)
        ->toHaveKey('scan_id', $scan->id)
        ->toHaveKey('property_name', $property->name)
        ->toHaveKey('total_violations', 12)
        ->toHaveKey('pages_scanned', 3);
});

it('sends scan completed notification via mail channel', function (): void {
    [$user, $agency, $organization, $property] = setupTenant();

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $notification = new ScanCompletedNotification($scan);
    $mail = $notification->toMail($user);

    expect($mail->subject)->toContain($property->name);
    expect($notification->via($user))->toContain('database', 'mail');
});

// ── IssueAssignedNotification ───────────────────────────────────────────────

it('sends issue assigned notification when issue is assigned', function (): void {
    Notification::fake();

    [$assigner, $agency, $organization, $property] = setupTenant();
    $assignee = User::factory()->create(['agency_id' => $agency->id]);

    $issue = Issue::factory()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'severity' => IssueSeverity::Critical,
    ]);

    $this->actingAs($assigner)
        ->postJson(route('api.issues.assign', $issue), ['user_id' => $assignee->id])
        ->assertOk();

    Notification::assertSentTo($assignee, IssueAssignedNotification::class, function ($notification) use ($issue, $assigner, $assignee) {
        $data = $notification->toArray($assignee);

        return $data['issue_id'] === $issue->id
            && $data['assigner_name'] === $assigner->name;
    });
});

it('does not send notification when issue is unassigned directly', function (): void {
    Notification::fake();

    [$user, $agency, $organization, $property] = setupTenant();

    $issue = Issue::factory()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'assigned_user_id' => $user->id,
    ]);

    $issue->unassignUser();

    Notification::assertNothingSent();
});

it('includes correct data in issue assigned notification', function (): void {
    [$assigner, $agency, $organization, $property] = setupTenant();
    $assignee = User::factory()->create(['agency_id' => $agency->id]);

    $issue = Issue::factory()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'severity' => IssueSeverity::High,
        'rule_key' => 'color-contrast',
    ]);

    $notification = new IssueAssignedNotification($issue, $assigner);
    $data = $notification->toArray($assignee);

    expect($data)
        ->toHaveKey('issue_id', $issue->id)
        ->toHaveKey('rule_key', 'color-contrast')
        ->toHaveKey('severity', 'high')
        ->toHaveKey('property_name', $property->name)
        ->toHaveKey('assigner_name', $assigner->name);
});

it('sends issue assigned notification via both channels', function (): void {
    [$assigner, $agency, $organization, $property] = setupTenant();
    $assignee = User::factory()->create(['agency_id' => $agency->id]);

    $issue = Issue::factory()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $notification = new IssueAssignedNotification($issue, $assigner);

    expect($notification->via($assignee))->toContain('database', 'mail');
});

// ── WeeklyDigestNotification ────────────────────────────────────────────────

it('sends weekly digest via mail channel only', function (): void {
    $user = User::factory()->create();

    $notification = new WeeklyDigestNotification([
        'agency_name' => 'Test Agency',
        'new_issues' => 10,
        'resolved_issues' => 5,
        'scans_completed' => 3,
        'assigned_open' => 2,
        'period_from' => '2025-01-01',
        'period_to' => '2025-01-07',
    ]);

    expect($notification->via($user))->toBe(['mail']);
});

it('includes digest stats in weekly digest mail', function (): void {
    $user = User::factory()->create();

    $notification = new WeeklyDigestNotification([
        'agency_name' => 'Test Agency',
        'new_issues' => 10,
        'resolved_issues' => 5,
        'scans_completed' => 3,
        'assigned_open' => 2,
        'period_from' => '2025-01-01',
        'period_to' => '2025-01-07',
    ]);

    $mail = $notification->toMail($user);

    expect($mail->subject)->toContain('Test Agency');
});

// ── Weekly Digest Command ───────────────────────────────────────────────────

it('sends weekly digest to all agency users', function (): void {
    Notification::fake();

    $agency = Agency::factory()->create();
    $user1 = User::factory()->create(['agency_id' => $agency->id]);
    $user2 = User::factory()->create(['agency_id' => $agency->id]);

    $this->artisan('digest:weekly')
        ->assertSuccessful();

    Notification::assertSentTo($user1, WeeklyDigestNotification::class);
    Notification::assertSentTo($user2, WeeklyDigestNotification::class);
});

it('scopes weekly digest to a specific agency', function (): void {
    Notification::fake();

    $agency1 = Agency::factory()->create();
    $agency2 = Agency::factory()->create();
    $user1 = User::factory()->create(['agency_id' => $agency1->id]);
    $user2 = User::factory()->create(['agency_id' => $agency2->id]);

    $this->artisan("digest:weekly --agency={$agency1->id}")
        ->assertSuccessful();

    Notification::assertSentTo($user1, WeeklyDigestNotification::class);
    Notification::assertNotSentTo($user2, WeeklyDigestNotification::class);
});

// ── Notification API ────────────────────────────────────────────────────────

it('returns paginated notifications for authenticated user', function (): void {
    [$user, $agency, $organization, $property] = setupTenant();

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $user->notify(new ScanCompletedNotification($scan));

    $this->actingAs($user)
        ->getJson(route('api.notifications.index'))
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'type', 'data', 'read_at', 'created_at']]]);
});

it('marks a notification as read', function (): void {
    [$user, $agency, $organization, $property] = setupTenant();

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $user->notify(new ScanCompletedNotification($scan));
    $notification = $user->notifications()->first();

    $this->actingAs($user)
        ->patchJson(route('api.notifications.read', $notification->id))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications as read', function (): void {
    [$user, $agency, $organization, $property] = setupTenant();

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $user->notify(new ScanCompletedNotification($scan));
    $user->notify(new ScanCompletedNotification($scan));

    $this->actingAs($user)
        ->postJson(route('api.notifications.read-all'))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('redirects guests from notification endpoints', function (): void {
    $this->getJson(route('api.notifications.index'))->assertUnauthorized();
    $this->postJson(route('api.notifications.read-all'))->assertUnauthorized();
});

it('prevents access to another users notification', function (): void {
    [$user1, $agency, $organization, $property] = setupTenant();
    $user2 = User::factory()->create(['agency_id' => $agency->id]);

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $user1->notify(new ScanCompletedNotification($scan));
    $notification = $user1->notifications()->first();

    $this->actingAs($user2)
        ->patchJson(route('api.notifications.read', $notification->id))
        ->assertNotFound();
});
