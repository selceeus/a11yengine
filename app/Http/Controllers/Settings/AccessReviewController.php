<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AccessReviewStatus;
use App\Enums\ActivityLogEvent;
use App\Http\Controllers\Controller;
use App\Models\AccessReview;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessReviewController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $reviews = AccessReview::query()
            ->with('completedBy:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AccessReview $review) => [
                'id' => $review->id,
                'period' => $review->period,
                'status' => $review->status->value,
                'due_at' => $review->due_at->toIso8601String(),
                'completed_at' => $review->completed_at?->toIso8601String(),
                'completed_by' => $review->completedBy ? [
                    'id' => $review->completedBy->id,
                    'name' => $review->completedBy->name,
                ] : null,
            ]);

        return Inertia::render('settings/access-reviews/index', [
            'reviews' => $reviews,
        ]);
    }

    public function show(AccessReview $accessReview): Response
    {
        $agencyId = auth()->user()->agency_id;

        $users = User::query()
            ->where('agency_id', $agencyId)
            ->with(['roles' => fn ($q) => $q->where('agency_id', $agencyId)->with(['organization:id,name', 'property:id,name'])])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                'roles' => $user->roles->map(fn (UserRole $role) => [
                    'id' => $role->id,
                    'role' => $role->role->value,
                    'organization' => $role->organization ? ['id' => $role->organization->id, 'name' => $role->organization->name] : null,
                    'property' => $role->property ? ['id' => $role->property->id, 'name' => $role->property->name] : null,
                ]),
            ]);

        return Inertia::render('settings/access-reviews/show', [
            'review' => [
                'id' => $accessReview->id,
                'period' => $accessReview->period,
                'status' => $accessReview->status->value,
                'due_at' => $accessReview->due_at->toIso8601String(),
                'completed_at' => $accessReview->completed_at?->toIso8601String(),
            ],
            'users' => $users,
        ]);
    }

    public function confirm(AccessReview $accessReview, User $user): RedirectResponse
    {
        ActivityLogger::log(
            event: ActivityLogEvent::UserAccessConfirmed,
            subject: $user,
            subjectLabel: $user->name,
            metadata: ['period' => $accessReview->period],
        );

        return redirect()->route('access-reviews.show', $accessReview);
    }

    public function revoke(AccessReview $accessReview, User $user): RedirectResponse
    {
        $agencyId = auth()->user()->agency_id;

        UserRole::where('user_id', $user->id)
            ->where('agency_id', $agencyId)
            ->delete();

        ActivityLogger::log(
            event: ActivityLogEvent::UserAccessRevoked,
            subject: $user,
            subjectLabel: $user->name,
            metadata: ['period' => $accessReview->period],
        );

        return redirect()->route('access-reviews.show', $accessReview);
    }

    public function complete(Request $request, AccessReview $accessReview): RedirectResponse
    {
        if ($accessReview->isCompleted()) {
            return redirect()->route('access-reviews.show', $accessReview);
        }

        $accessReview->update([
            'status' => AccessReviewStatus::Completed,
            'completed_at' => now(),
            'completed_by' => $request->user()->id,
        ]);

        ActivityLogger::log(
            event: ActivityLogEvent::AccessReviewCompleted,
            subject: $accessReview,
            subjectLabel: $accessReview->period,
        );

        return redirect()->route('access-reviews.index')->with('success', 'Access review completed.');
    }
}
