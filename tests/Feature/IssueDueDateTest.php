<?php

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\IssueActivity;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);
    $this->actor = User::factory()->create(['agency_id' => $this->agency->id]);
});

// ── auto-set on create ────────────────────────────────────────────────────────

it('auto-sets due_date on a critical issue to 7 days from first_detected_at', function (): void {
    $now = now();

    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::Critical,
        'first_detected_at' => $now,
    ]);

    expect($issue->fresh()->due_date?->toDateString())->toBe($now->addDays(7)->toDateString());
});

it('auto-sets due_date on a high issue to 14 days', function (): void {
    $now = now();

    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::High,
        'first_detected_at' => $now,
    ]);

    expect($issue->fresh()->due_date?->toDateString())->toBe($now->addDays(14)->toDateString());
});

it('auto-sets due_date on a medium issue to 30 days', function (): void {
    $now = now();

    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::Medium,
        'first_detected_at' => $now,
    ]);

    expect($issue->fresh()->due_date?->toDateString())->toBe($now->addDays(30)->toDateString());
});

it('auto-sets due_date on a low issue to 60 days', function (): void {
    $now = now();

    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::Low,
        'first_detected_at' => $now,
    ]);

    expect($issue->fresh()->due_date?->toDateString())->toBe($now->addDays(60)->toDateString());
});

it('does not overwrite an explicitly provided due_date', function (): void {
    $explicitDate = now()->addDays(100)->toDateString();

    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::Critical,
        'due_date' => $explicitDate,
    ]);

    expect($issue->fresh()->due_date?->toDateString())->toBe($explicitDate);
});

// ── isOverdue ─────────────────────────────────────────────────────────────────

it('isOverdue returns true when due_date is past and status is non-terminal', function (): void {
    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'due_date' => now()->subDay(),
    ]);

    expect($issue->isOverdue())->toBeTrue();
});

it('isOverdue returns false when due_date is in the future', function (): void {
    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'due_date' => now()->addDays(3),
    ]);

    expect($issue->isOverdue())->toBeFalse();
});

it('isOverdue returns false when issue is resolved even if past due', function (): void {
    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Resolved,
        'due_date' => now()->subDay(),
    ]);

    expect($issue->isOverdue())->toBeFalse();
});

it('isOverdue returns false when due_date is null', function (): void {
    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'due_date' => null,
    ]);

    expect($issue->isOverdue())->toBeFalse();
});

// ── overdue scope ─────────────────────────────────────────────────────────────

it('overdue scope returns only overdue non-terminal issues', function (): void {
    $overdueIssue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'due_date' => now()->subDay(),
    ]);

    $futureIssue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'due_date' => now()->addDays(5),
    ]);

    $resolvedOverdueIssue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Resolved,
        'due_date' => now()->subDay(),
    ]);

    $results = Issue::overdue()->pluck('id');

    expect($results)->toContain($overdueIssue->id)
        ->not->toContain($futureIssue->id)
        ->not->toContain($resolvedOverdueIssue->id);
});

// ── update via controller ─────────────────────────────────────────────────────

it('can update due_date via the issue update route', function (): void {
    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    $newDate = now()->addDays(20)->toDateString();

    $this->actingAs($this->actor)
        ->patch(route('issues.update', $issue), ['due_date' => $newDate])
        ->assertRedirect(route('issues.show', $issue));

    expect($issue->fresh()->due_date?->toDateString())->toBe($newDate);
});

it('logs a due_date_change activity when due_date is updated via controller', function (): void {
    $this->actingAs($this->actor);

    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'due_date' => now()->addDays(7),
    ]);

    $newDate = now()->addDays(30)->toDateString();

    $this->actingAs($this->actor)
        ->patch(route('issues.update', $issue), ['due_date' => $newDate]);

    expect(
        IssueActivity::where('issue_id', $issue->id)
            ->where('type', 'due_date_change')
            ->exists()
    )->toBeTrue();
});
