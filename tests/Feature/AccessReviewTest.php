<?php

use App\Enums\UserRole;
use App\Models\AccessReview;
use App\Models\ActivityLog;
use App\Models\Agency;
use App\Models\User;
use App\Models\UserRole as UserRoleModel;
use App\Notifications\AccessReviewDueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->admin = User::factory()
        ->withRole(UserRole::AgencyAdmin, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);
});

it('creates one access review per agency via command', function (): void {
    Notification::fake();

    $this->artisan('access-reviews:create')->assertSuccessful();

    expect(AccessReview::withoutGlobalScopes()->count())->toBe(1);

    $review = AccessReview::withoutGlobalScopes()->first();
    expect($review->agency_id)->toBe($this->agency->id)
        ->and($review->status)->toBe('pending');
});

it('sends AccessReviewDueNotification to agency admins on create', function (): void {
    Notification::fake();

    $this->artisan('access-reviews:create')->assertSuccessful();

    Notification::assertSentTo($this->admin, AccessReviewDueNotification::class);
});

it('does not create duplicate for the same period', function (): void {
    Notification::fake();

    $this->artisan('access-reviews:create')->assertSuccessful();
    $this->artisan('access-reviews:create')->assertSuccessful();

    expect(AccessReview::withoutGlobalScopes()->count())->toBe(1);
});

it('logs UserAccessConfirmed when confirming a user', function (): void {
    $review = AccessReview::factory()->create(['agency_id' => $this->agency->id, 'status' => 'pending']);
    $member = User::factory()
        ->withRole(UserRole::Editor, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);

    $this->actingAs($this->admin)
        ->post("/settings/access-reviews/{$review->id}/users/{$member->id}/confirm");

    $log = ActivityLog::where('event', 'access_review.user_confirmed')->first();
    expect($log)->not->toBeNull()
        ->and($log->agency_id)->toBe($this->agency->id);
});

it('removes roles and logs UserAccessRevoked when revoking a user', function (): void {
    $review = AccessReview::factory()->create(['agency_id' => $this->agency->id, 'status' => 'pending']);
    $member = User::factory()
        ->withRole(UserRole::Editor, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);

    expect(UserRoleModel::where('user_id', $member->id)->where('agency_id', $this->agency->id)->count())->toBe(1);

    $this->actingAs($this->admin)
        ->post("/settings/access-reviews/{$review->id}/users/{$member->id}/revoke");

    expect(UserRoleModel::where('user_id', $member->id)->where('agency_id', $this->agency->id)->count())->toBe(0);

    $log = ActivityLog::where('event', 'access_review.user_revoked')->first();
    expect($log)->not->toBeNull();
});

it('marks review as completed and logs AccessReviewCompleted', function (): void {
    $review = AccessReview::factory()->create(['agency_id' => $this->agency->id, 'status' => 'pending']);

    $this->actingAs($this->admin)
        ->post("/settings/access-reviews/{$review->id}/complete")
        ->assertRedirect('/settings/access-reviews');

    $review->refresh();
    expect($review->status)->toBe('completed')
        ->and($review->completed_at)->not->toBeNull()
        ->and($review->completed_by)->toBe($this->admin->id);

    $log = ActivityLog::where('event', 'access_review.completed')->first();
    expect($log)->not->toBeNull();
});
