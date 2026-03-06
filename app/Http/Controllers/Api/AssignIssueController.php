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
        /** @var User $assignee */
        $assignee = User::findOrFail($request->validated()['user_id']);

        $issue->assignToUser($assignee);

        return response()->json(
            $issue->fresh(['assignedUser:id,name,email']),
        );
    }
}
