<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignIssueRequest;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AssignIssueController extends Controller
{
    public function __invoke(AssignIssueRequest $request, Issue $issue): JsonResponse
    {
        $userId = $request->validated()['user_id'];

        if ($userId === null) {
            $issue->unassignUser();
        } else {
            /** @var User $assignee */
            $assignee = User::findOrFail($userId);
            $issue->assignToUser($assignee);
        }

        return response()->json(
            $issue->fresh(['assignedUser:id,name,email']),
        );
    }
}
