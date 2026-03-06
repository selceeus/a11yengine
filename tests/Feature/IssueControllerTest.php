<?php

use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

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
        'assigned_user_id' => null,
    ]);
    $this->actor = User::factory()->create(['agency_id' => $this->agency->id]);
});

// ── show ──────────────────────────────────────────────────────────────────────

it('renders the issue show page for an authenticated user', function (): void {
    $this->actingAs($this->actor)
        ->get(route('issues.show', $this->issue))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('issues/show')
            ->has('issue')
            ->has('assignableUsers')
        );
});

it('passes the issue data to the show page', function (): void {
    $this->actingAs($this->actor)
        ->get(route('issues.show', $this->issue))
        ->assertInertia(fn (Assert $page) => $page
            ->where('issue.id', $this->issue->id)
            ->where('issue.rule_key', $this->issue->rule_key)
        );
});

it('includes assigned_user in the issue data when assigned', function (): void {
    $assignee = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->issue->update(['assigned_user_id' => $assignee->id]);

    $this->actingAs($this->actor)
        ->get(route('issues.show', $this->issue))
        ->assertInertia(fn (Assert $page) => $page
            ->where('issue.assigned_user_id', $assignee->id)
            ->has('issue.assigned_user')
            ->where('issue.assigned_user.id', $assignee->id)
            ->where('issue.assigned_user.name', $assignee->name)
            ->where('issue.assigned_user.email', $assignee->email)
        );
});

it('passes assignable users scoped to the same agency', function (): void {
    $sameAgencyUser = User::factory()->create(['agency_id' => $this->agency->id]);
    $otherAgency = Agency::factory()->create();
    User::factory()->create(['agency_id' => $otherAgency->id]);

    $this->actingAs($this->actor)
        ->get(route('issues.show', $this->issue))
        ->assertInertia(fn (Assert $page) => $page
            ->has('assignableUsers', 2)
        );
});

it('does not include users from other agencies in assignableUsers', function (): void {
    $otherAgency = Agency::factory()->create();
    User::factory()->count(3)->create(['agency_id' => $otherAgency->id]);

    // Only the actor belongs to $this->agency
    $this->actingAs($this->actor)
        ->get(route('issues.show', $this->issue))
        ->assertInertia(fn (Assert $page) => $page
            ->has('assignableUsers', 1)
            ->where('assignableUsers.0.id', $this->actor->id)
        );
});

it('returns 404 when a user from another agency tries to view the issue', function (): void {
    $outsider = User::factory()->create(['agency_id' => Agency::factory()->create()->id]);

    $this->actingAs($outsider)
        ->get(route('issues.show', $this->issue))
        ->assertNotFound();
});

it('redirects unauthenticated users to login', function (): void {
    $this->get(route('issues.show', $this->issue))
        ->assertRedirect(route('login'));
});
