<?php

namespace App\Http\Controllers\Api;

use App\Domain\Issues\IssueSeveritySummary;
use App\Enums\IssueStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyIssueSummaryController extends Controller
{
    public function __invoke(Request $request, Agency $agency): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $agency->id, 403);

        $query = Issue::withoutGlobalScopes()
            ->where('agency_id', $agency->id)
            ->whereIn('status', IssueStatus::activeStatusValues());

        if (! $user->isSuperUser() && $user->hasRole(UserRole::PropAdmin)) {
            $propertyIds = $user->roles()
                ->where('role', UserRole::PropAdmin->value)
                ->whereNotNull('property_id')
                ->pluck('property_id');

            $query->whereIn('property_id', $propertyIds);
        }

        /** @var array<string, int> $counts */
        $counts = $query
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        return response()->json(IssueSeveritySummary::fromCounts($counts));
    }
}
