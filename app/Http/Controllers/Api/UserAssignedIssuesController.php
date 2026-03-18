<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAssignedIssuesController extends Controller
{
    public function __invoke(Request $request, User $user): JsonResponse
    {
        /** @var Agency $agency */
        $agency = app(Agency::class);

        abort_unless($user->agency_id === $agency->id, 403);

        $issues = Issue::query()
            ->with(['property:id,name'])
            ->where('assigned_user_id', $user->id)
            ->select(['id', 'rule_key', 'severity', 'status', 'property_id', 'occurrence_count', 'last_detected_at'])
            ->latest('last_detected_at')
            ->get();

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name],
            'issues' => $issues,
        ]);
    }
}
