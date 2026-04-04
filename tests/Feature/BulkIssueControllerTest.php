<?php

use App\Enums\IssueStatus;
use App\Enums\UserRole as UserRoleEnum;
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
    $this->actor = User::factory()->withRole(UserRoleEnum::AgencyAdmin, agencyId: $this->agency->id)->create(['agency_id' => $this->agency->id]);

    $this->issues = Issue::factory()->count(3)->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
    ]);
});

function bulkPost(array $data): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->actor)
        ->postJson('/api/issues/bulk', $data);
}

// ── status change ─────────────────────────────────────────────────────────────

it('bulk changes status on selected issues', function (): void {
    $ids = $this->issues->pluck('id')->all();

    bulkPost(['ids' => $ids, 'action' => 'status_change', 'status' => 'in_progress'])
        ->assertOk()
        ->assertJsonPath('affected', 3);

    foreach ($ids as $id) {
        expect(Issue::withoutGlobalScopes()->find($id)?->status)->toBe(IssueStatus::InProgress);
    }
});

// ── assign ────────────────────────────────────────────────────────────────────

it('bulk assigns issues to a user', function (): void {
    $assignee = User::factory()->create(['agency_id' => $this->agency->id]);
    $ids = $this->issues->pluck('id')->all();

    bulkPost(['ids' => $ids, 'action' => 'assign', 'user_id' => $assignee->id])
        ->assertOk();

    foreach ($ids as $id) {
        expect(Issue::withoutGlobalScopes()->find($id)?->assigned_user_id)->toBe($assignee->id);
    }
});

it('bulk unassigns issues when user_id is null', function (): void {
    $assignee = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->issues->each(fn ($i) => $i->update(['assigned_user_id' => $assignee->id]));

    $ids = $this->issues->pluck('id')->all();

    bulkPost(['ids' => $ids, 'action' => 'assign', 'user_id' => null])
        ->assertOk();

    foreach ($ids as $id) {
        expect(Issue::withoutGlobalScopes()->find($id)?->assigned_user_id)->toBeNull();
    }
});

// ── ignore ────────────────────────────────────────────────────────────────────

it('bulk ignores issues', function (): void {
    $ids = $this->issues->pluck('id')->all();

    bulkPost(['ids' => $ids, 'action' => 'ignore'])
        ->assertOk();

    foreach ($ids as $id) {
        expect(Issue::withoutGlobalScopes()->find($id)?->status)->toBe(IssueStatus::Ignored);
    }
});

// ── due date ─────────────────────────────────────────────────────────────────

it('bulk sets due date', function (): void {
    $ids = $this->issues->pluck('id')->all();
    $date = now()->addDays(14)->toDateString();

    bulkPost(['ids' => $ids, 'action' => 'set_due_date', 'due_date' => $date])
        ->assertOk();

    foreach ($ids as $id) {
        expect(Issue::withoutGlobalScopes()->find($id)?->due_date?->toDateString())->toBe($date);
    }
});

// ── delete (soft) ─────────────────────────────────────────────────────────────

it('bulk soft-deletes issues', function (): void {
    $ids = $this->issues->pluck('id')->all();

    bulkPost(['ids' => $ids, 'action' => 'delete'])
        ->assertOk()
        ->assertJsonPath('affected', 3);

    foreach ($ids as $id) {
        expect(Issue::withoutGlobalScopes()->withTrashed()->find($id)?->deleted_at)->not->toBeNull();
    }
});

// ── tenant isolation ──────────────────────────────────────────────────────────

it('cannot bulk-update issues belonging to another agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    $otherProperty = Property::factory()->create(['agency_id' => $otherAgency->id, 'organization_id' => $otherOrg->id]);
    $otherIssue = Issue::factory()->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
        'property_id' => $otherProperty->id,
    ]);

    bulkPost(['ids' => [$otherIssue->id], 'action' => 'ignore'])
        ->assertOk()
        ->assertJsonPath('affected', 0); // TenantScope prevents cross-agency match
});

// ── validation ────────────────────────────────────────────────────────────────

it('requires ids and action', function (): void {
    bulkPost([])->assertUnprocessable();
});

it('requires authentication', function (): void {
    $this->postJson('/api/issues/bulk', ['ids' => [1], 'action' => 'ignore'])
        ->assertUnauthorized();
});

// ── activity logging ──────────────────────────────────────────────────────────

it('logs a bulk_action activity for each affected issue', function (): void {
    $ids = $this->issues->pluck('id')->all();

    bulkPost(['ids' => $ids, 'action' => 'status_change', 'status' => 'resolved']);

    expect(IssueActivity::whereIn('issue_id', $ids)->where('type', 'bulk_action')->count())->toBe(3);
});
