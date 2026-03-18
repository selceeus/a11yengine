<?php

use App\Enums\IssueStatus;
use App\Jobs\GenerateIssueRemediationJob;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();
    $this->issue = Issue::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create(['severity' => 'low']);

    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($this->user);
});

// ─── index ──────────────────────────────────────────────────────────────────

it('returns the issues index page', function (): void {
    $this->get(route('issues.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('issues/index'));
});

it('passes paginated issues belonging to the agency', function (): void {
    $this->get(route('issues.index'))
        ->assertInertia(fn ($page) => $page->has('issues.data', 1));
});

it('does not expose issues from another agency on the index', function (): void {
    Issue::factory()->create();

    $this->get(route('issues.index'))
        ->assertInertia(fn ($page) => $page->has('issues.data', 1));
});

it('filters issues by status', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)
        ->create(['status' => IssueStatus::Resolved]);

    $this->get(route('issues.index', ['status' => 'open']))
        ->assertInertia(fn ($page) => $page->has('issues.data', 1));
});

it('filters issues by severity', function (): void {
    Issue::factory()->for($this->agency)->for($this->organization)->for($this->property)
        ->create(['severity' => 'critical']);

    $this->get(route('issues.index', ['severity' => 'critical']))
        ->assertInertia(fn ($page) => $page->has('issues.data', 1));
});

it('filters issues by property', function (): void {
    $otherProperty = Property::factory()->for($this->agency)->for($this->organization)->create();
    Issue::factory()->for($this->agency)->for($this->organization)->for($otherProperty)->create();

    $this->get(route('issues.index', ['property_id' => $this->property->id]))
        ->assertInertia(fn ($page) => $page->has('issues.data', 1));
});

it('redirects unauthenticated users from the index', function (): void {
    $this->post('/logout');

    $this->get(route('issues.index'))->assertRedirect(route('login'));
});

// ─── show ─────────────────────────────────────────────────────────────────────

it('returns the issue show page', function (): void {
    $this->get(route('issues.show', $this->issue))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('issues/show'));
});

it('passes the issue with relationships to show', function (): void {
    $this->get(route('issues.show', $this->issue))
        ->assertInertia(fn ($page) => $page
            ->has('issue')
            ->where('issue.id', $this->issue->id)
            ->has('issue.property')
            ->has('issue.findings')
        );
});

it('returns 404 when viewing an issue from another agency', function (): void {
    $otherIssue = Issue::factory()->create();

    $this->get(route('issues.show', $otherIssue))->assertNotFound();
});

it('redirects unauthenticated users from show', function (): void {
    $this->post('/logout');

    $this->get(route('issues.show', $this->issue))->assertRedirect(route('login'));
});

// ─── update ───────────────────────────────────────────────────────────────────

it('updates the issue status', function (): void {
    $this->patch(route('issues.update', $this->issue), ['status' => 'in_progress'])
        ->assertRedirect(route('issues.show', $this->issue));

    expect($this->issue->fresh()->status)->toBe(IssueStatus::InProgress);
});

it('sets resolved_at when status is changed to resolved', function (): void {
    $this->patch(route('issues.update', $this->issue), ['status' => 'resolved']);

    expect($this->issue->fresh()->resolved_at)->not->toBeNull();
});

it('clears resolved_at when status is changed away from resolved', function (): void {
    $this->issue->update(['status' => IssueStatus::Resolved, 'resolved_at' => now()]);

    $this->patch(route('issues.update', $this->issue), ['status' => 'open']);

    expect($this->issue->fresh()->resolved_at)->toBeNull();
});

it('returns a validation error for an invalid status', function (): void {
    $this->patch(route('issues.update', $this->issue), ['status' => 'invalid'])
        ->assertSessionHasErrors('status');
});

it('returns 404 when updating an issue from another agency', function (): void {
    $otherIssue = Issue::factory()->create();

    $this->patch(route('issues.update', $otherIssue), ['status' => 'open'])->assertNotFound();
});

// ─── generateRemediation ──────────────────────────────────────────────────────

it('dispatches remediation job and marks issue pending', function (): void {
    Queue::fake();

    $this->post(route('issues.remediation.generate', $this->issue))
        ->assertRedirect(route('issues.show', $this->issue));

    expect($this->issue->fresh()->ai_remediation_status)->toBe('pending');
    Queue::assertPushed(GenerateIssueRemediationJob::class);
});

it('returns 404 for remediation of a cross-agency issue', function (): void {
    Queue::fake();

    $otherIssue = Issue::factory()->create();

    $this->post(route('issues.remediation.generate', $otherIssue))->assertNotFound();
    Queue::assertNothingPushed();
});

it('redirects unauthenticated users from remediation', function (): void {
    $this->post('/logout');

    $this->post(route('issues.remediation.generate', $this->issue))
        ->assertRedirect(route('login'));
});
