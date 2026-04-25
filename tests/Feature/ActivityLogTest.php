<?php

use App\Enums\ActivityLogEvent;
use App\Models\ActivityLog;
use App\Models\Agency;
use App\Models\ApiKey;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->user = User::factory()
        ->withRole(\App\Enums\UserRole::AgencyAdmin, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);
});

// ── ActivityLogger::loginSuccess ─────────────────────────────────────────────

it('logs a login event', function (): void {
    $request = Request::create('/login', 'POST', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    ActivityLogger::loginSuccess($this->user, $request);

    $log = ActivityLog::first();
    expect($log)->not->toBeNull()
        ->and($log->event)->toBe(ActivityLogEvent::UserLogin)
        ->and($log->actor_type)->toBe('user')
        ->and($log->actor_label)->toBe($this->user->name)
        ->and($log->agency_id)->toBe($this->agency->id)
        ->and($log->ip_address)->toBe('1.2.3.4');
});

// ── ActivityLogger::logoutSuccess ────────────────────────────────────────────

it('logs a logout event', function (): void {
    $request = Request::create('/logout', 'POST', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    ActivityLogger::logoutSuccess($this->user, $request);

    $log = ActivityLog::first();
    expect($log)->not->toBeNull()
        ->and($log->event)->toBe(ActivityLogEvent::UserLogout)
        ->and($log->actor_type)->toBe('user')
        ->and($log->agency_id)->toBe($this->agency->id);
});

// ── ActivityLogger::log (generic web user) ───────────────────────────────────

it('logs a generic user event when acting as authenticated user', function (): void {
    $this->actingAs($this->user);

    ActivityLogger::log(ActivityLogEvent::ApiKeyCreated, subjectLabel: 'My Key');

    $log = ActivityLog::first();
    expect($log)->not->toBeNull()
        ->and($log->event)->toBe(ActivityLogEvent::ApiKeyCreated)
        ->and($log->subject_label)->toBe('My Key')
        ->and($log->user_id)->toBe($this->user->id);
});

// ── ActivityLogger::system ───────────────────────────────────────────────────

it('logs a system event without a user', function (): void {
    ActivityLogger::system(
        agencyId: $this->agency->id,
        event: ActivityLogEvent::ScanCompleted,
        subjectLabel: 'My Property',
        metadata: ['pages_scanned' => 5],
    );

    $log = ActivityLog::first();
    expect($log)->not->toBeNull()
        ->and($log->event)->toBe(ActivityLogEvent::ScanCompleted)
        ->and($log->actor_type)->toBe('system')
        ->and($log->user_id)->toBeNull()
        ->and($log->metadata)->toHaveKey('pages_scanned', 5);
});

// ── Login event fires automatically ──────────────────────────────────────────

it('creates an activity log on login via auth event', function (): void {
    $this->post('/login', [
        'email' => $this->user->email,
        'password' => 'password',
    ]);

    expect(ActivityLog::where('event', ActivityLogEvent::UserLogin->value)->exists())->toBeTrue();
});

// ── IssueCommentController creates activity log ──────────────────────────────

it('creates an activity log when a comment is posted', function (): void {
    $organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $property = Property::factory()->create(['agency_id' => $this->agency->id, 'organization_id' => $organization->id]);
    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('issues.comments.store', $issue), ['body' => 'A comment']);

    expect(ActivityLog::where('event', ActivityLogEvent::IssueCommentAdded->value)->exists())->toBeTrue();
});

// ── ApiKeyController creates activity log on store ────────────────────────────

it('creates an activity log when an api key is created', function (): void {
    $this->actingAs($this->user)
        ->post(route('api-keys.store'), [
            'name' => 'Test Key',
            'scopes' => ['scans:read'],
            'expires_at' => null,
        ]);

    expect(ActivityLog::where('event', ActivityLogEvent::ApiKeyCreated->value)->exists())->toBeTrue();
});

// ── ApiKeyController creates activity log on destroy ─────────────────────────

it('creates an activity log when an api key is revoked', function (): void {
    $token = ApiKey::generateToken();
    $apiKey = ApiKey::create([
        'agency_id' => $this->agency->id,
        'created_by' => $this->user->id,
        'name' => 'Revoke Me',
        'key_prefix' => $token['prefix'],
        'token_hash' => $token['hash'],
        'scopes' => ['scans:read'],
    ]);

    $this->actingAs($this->user)
        ->delete(route('api-keys.destroy', $apiKey));

    expect(ActivityLog::where('event', ActivityLogEvent::ApiKeyRevoked->value)->exists())->toBeTrue();
});

// ── TenantScope: activity feed is scoped to agency ───────────────────────────

it('does not return activity logs from other agencies', function (): void {
    $otherAgency = Agency::factory()->create();
    ActivityLogger::system(agencyId: $otherAgency->id, event: ActivityLogEvent::ScanFailed);

    $this->actingAs($this->user)
        ->getJson(route('api.activity-feed'))
        ->assertJsonCount(0, 'data');
});

// ── ActivityFeedController returns logs for agency ───────────────────────────

it('returns activity logs for the authenticated user agency', function (): void {
    ActivityLogger::system(agencyId: $this->agency->id, event: ActivityLogEvent::ScanCompleted, subjectLabel: 'My Prop');

    $this->actingAs($this->user)
        ->getJson(route('api.activity-feed'))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event', ActivityLogEvent::ScanCompleted->value);
});

// ── ActivityLogExport returns a CSV ──────────────────────────────────────────

it('exports activity logs as csv', function (): void {
    ActivityLogger::system(agencyId: $this->agency->id, event: ActivityLogEvent::ScanCompleted, subjectLabel: 'Prop');

    $response = $this->actingAs($this->user)
        ->get(route('activity-log.export'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
