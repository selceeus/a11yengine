<?php

use App\Enums\IssueActivityType;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\IssueActivity;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Notifications\IssueMentionedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);
    $this->issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);
    $this->actor = User::factory()
        ->withRole(UserRole::Editor, $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);
});

// ── store ─────────────────────────────────────────────────────────────────────

it('stores a comment on an issue', function (): void {
    $this->actingAs($this->actor)
        ->post(route('issues.comments.store', $this->issue), ['body' => 'This looks like a real issue.'])
        ->assertRedirect(route('issues.show', $this->issue));

    expect(IssueActivity::where('issue_id', $this->issue->id)->where('type', IssueActivityType::Comment->value)->count())->toBe(1);

    $activity = IssueActivity::where('issue_id', $this->issue->id)->first();
    expect($activity->body)->toBe('This looks like a real issue.')
        ->and($activity->user_id)->toBe($this->actor->id);
});

it('requires a non-empty body', function (): void {
    $this->actingAs($this->actor)
        ->post(route('issues.comments.store', $this->issue), ['body' => ''])
        ->assertSessionHasErrors('body');
});

it('rejects a body longer than 2000 characters', function (): void {
    $this->actingAs($this->actor)
        ->post(route('issues.comments.store', $this->issue), ['body' => str_repeat('a', 2001)])
        ->assertSessionHasErrors('body');
});

it('requires authentication to post a comment', function (): void {
    $this->post(route('issues.comments.store', $this->issue), ['body' => 'Hi'])
        ->assertRedirect(route('login'));
});

it('cannot comment on another agency\'s issue', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    $otherProperty = Property::factory()->create(['agency_id' => $otherAgency->id, 'organization_id' => $otherOrg->id]);
    $otherIssue = Issue::factory()->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
        'property_id' => $otherProperty->id,
    ]);

    // TenantScope means the issue is not found for our actor → 404
    $this->actingAs($this->actor)
        ->post(route('issues.comments.store', $otherIssue), ['body' => 'Sneaky comment'])
        ->assertNotFound();
});

// ── @mentions ─────────────────────────────────────────────────────────────────

it('sends a mention notification when a team member is @mentioned by first name', function (): void {
    Notification::fake();

    $mentioned = User::factory()->create(['agency_id' => $this->agency->id, 'name' => 'Alice Smith']);

    $this->actingAs($this->actor)
        ->post(route('issues.comments.store', $this->issue), ['body' => 'Hey @Alice can you look at this?']);

    Notification::assertSentTo($mentioned, IssueMentionedNotification::class);
});

it('does not notify the commenter even if they mention themselves', function (): void {
    Notification::fake();

    $this->actor->update(['name' => 'Bob Jones']);

    $this->actingAs($this->actor)
        ->post(route('issues.comments.store', $this->issue), ['body' => 'Reminder for @Bob']);

    Notification::assertNothingSent();
});

it('does not notify users in other agencies', function (): void {
    Notification::fake();

    User::factory()->create(['name' => 'Carol Lee']); // different agency

    $this->actingAs($this->actor)
        ->post(route('issues.comments.store', $this->issue), ['body' => 'Hey @Carol']);

    Notification::assertNothingSent();
});

// ── observer auto-log ─────────────────────────────────────────────────────────

it('auto-logs a status change activity via observer', function (): void {
    $this->actingAs($this->actor);

    $this->issue->update(['status' => \App\Enums\IssueStatus::InProgress]);

    $activity = IssueActivity::where('issue_id', $this->issue->id)
        ->where('type', IssueActivityType::StatusChange->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->metadata['from'])->toBe('open')
        ->and($activity->metadata['to'])->toBe('in_progress');
});

it('auto-logs an assignment change activity via observer', function (): void {
    $this->actingAs($this->actor);

    $assignee = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->issue->update(['assigned_user_id' => $assignee->id]);

    $activity = IssueActivity::where('issue_id', $this->issue->id)
        ->where('type', IssueActivityType::Assignment->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->metadata['to_user_id'])->toBe($assignee->id);
});
