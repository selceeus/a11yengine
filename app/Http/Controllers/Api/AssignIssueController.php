<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationEmailCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignIssueRequest;
use App\Models\Issue;
use App\Models\User;
use App\Notifications\IssueAssignedNotification;
use App\Services\RoutedEmailNotifier;
use App\Services\WebhookNotifier;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class AssignIssueController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(AssignIssueRequest $request, Issue $issue): JsonResponse
    {
        $this->authorize('update', $issue);

        $userId = $request->validated('user_id');

        if ($userId === null) {
            $issue->unassignUser();
        } else {
            /** @var User $assignee */
            $assignee = User::findOrFail($userId);
            $issue->assignToUser($assignee);

            /** @var User $assigner */
            $assigner = $request->user();
            $assignee->notify(new IssueAssignedNotification($issue, $assigner));

            $title = "Issue assigned: {$issue->rule_key}";
            $body = "{$issue->severity?->value} severity issue on {$issue->property?->name} assigned to {$assignee->name} by {$assigner->name}.";

            app(RoutedEmailNotifier::class)->notify(
                $issue->agency_id,
                NotificationEmailCategory::Issues->value,
                new IssueAssignedNotification($issue, $assigner),
            );

            app(WebhookNotifier::class)->notify(
                $issue->agency_id,
                NotificationEmailCategory::Issues->value,
                $title,
                $body,
            );
        }

        return response()->json(
            $issue->fresh(['assignedUser:id,name,email']),
        );
    }
}
