<?php

use App\Enums\IssueStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

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
        'status' => IssueStatus::Open,
        'assigned_user_id' => null,
    ]);
    $this->actor = User::factory()->withRole(UserRole::AgencyAdmin, agencyId: $this->agency->id)->create(['agency_id' => $this->agency->id]);
    $this->assignee = User::factory()->create(['agency_id' => $this->agency->id]);
});

// ── Authentication ────────────────────────────────────────────────────────────

it('returns 401 for unauthenticated requests', function (): void {
    $this->postJson(route('api.issues.assign', $this->issue->id), ['user_id' => $this->assignee->id])
        ->assertUnauthorized();
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('returns 404 when the acting user belongs to a different agency (TenantScope hides the issue)', function (): void {
    $outsider = User::factory()->create(['agency_id' => Agency::factory()->create()->id]);

    // TenantScope filters issues to the acting user's agency_id, so the
    // route model binding cannot resolve the issue — 404, not 403.
    $this->actingAs($outsider)
        ->postJson(route('api.issues.assign', $this->issue->id), ['user_id' => $this->assignee->id])
        ->assertNotFound();
});

it('allows a super user to assign regardless of agency', function (): void {
    $superUser = User::factory()->create(['agency_id' => null]);
    $superUser->roles()->create(['role' => UserRole::SuperUser->value]);

    $this->actingAs($superUser)
        ->postJson(route('api.issues.assign', $this->issue->id), ['user_id' => $this->assignee->id])
        ->assertOk();
});

// ── Successful assignment ─────────────────────────────────────────────────────

it('assigns the issue to the specified user', function (): void {
    $this->actingAs($this->actor)
        ->postJson(route('api.issues.assign', $this->issue->id), ['user_id' => $this->assignee->id])
        ->assertOk();

    expect($this->issue->fresh()->assigned_user_id)->toBe($this->assignee->id);
});

it('transitions an open issue to in_progress on assignment', function (): void {
    $this->actingAs($this->actor)
        ->postJson(route('api.issues.assign', $this->issue->id), ['user_id' => $this->assignee->id])
        ->assertOk();

    expect($this->issue->fresh()->status)->toBe(IssueStatus::InProgress);
});

it('does not change the status when the issue is already in a non-open state', function (): void {
    $this->issue->update(['status' => IssueStatus::InProgress]);

    $this->actingAs($this->actor)
        ->postJson(route('api.issues.assign', $this->issue->id), ['user_id' => $this->assignee->id])
        ->assertOk();

    expect($this->issue->fresh()->status)->toBe(IssueStatus::InProgress);
});

it('returns the updated issue JSON with assignedUser relationship', function (): void {
    $response = $this->actingAs($this->actor)
        ->postJson(route('api.issues.assign', $this->issue->id), ['user_id' => $this->assignee->id])
        ->assertOk();

    $response->assertJsonPath('id', $this->issue->id)
        ->assertJsonPath('assigned_user_id', $this->assignee->id)
        ->assertJsonStructure(['id', 'assigned_user_id', 'status', 'assigned_user' => ['id', 'name', 'email']]);
});

it('can reassign an already-assigned issue to a different user', function (): void {
    $firstAssignee = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->issue->assignToUser($firstAssignee);

    $this->actingAs($this->actor)
        ->postJson(route('api.issues.assign', $this->issue->id), ['user_id' => $this->assignee->id])
        ->assertOk();

    expect($this->issue->fresh()->assigned_user_id)->toBe($this->assignee->id);
});

// ── Invalid user assignment ───────────────────────────────────────────────────

it('returns 422 when user_id is missing', function (): void {
    $this->actingAs($this->actor)
        ->postJson(route('api.issues.assign', $this->issue->id), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);
});

it('returns 422 when user_id is not an integer', function (): void {
    $this->actingAs($this->actor)
        ->postJson(route('api.issues.assign', $this->issue->id), ['user_id' => 'not-an-id'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);
});

it('returns 422 when user_id does not exist in the database', function (): void {
    $this->actingAs($this->actor)
        ->postJson(route('api.issues.assign', $this->issue->id), ['user_id' => 99999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);
});

it('returns 422 when the assignee belongs to a different agency', function (): void {
    $foreignUser = User::factory()->create(['agency_id' => Agency::factory()->create()->id]);

    $this->actingAs($this->actor)
        ->postJson(route('api.issues.assign', $this->issue->id), ['user_id' => $foreignUser->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);
});

it('returns a descriptive validation message for a cross-agency user_id', function (): void {
    $foreignUser = User::factory()->create(['agency_id' => Agency::factory()->create()->id]);

    $response = $this->actingAs($this->actor)
        ->postJson(route('api.issues.assign', $this->issue->id), ['user_id' => $foreignUser->id])
        ->assertUnprocessable();

    expect($response->json('errors.user_id.0'))
        ->toContain('does not belong to the same agency');
});
