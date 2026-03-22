<?php

namespace App\Http\Controllers\Api;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgIssueSummaryController extends Controller
{
    public function __invoke(Request $request, Organization $organization): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $organization->agency_id, 403);

        $propertyIds = Property::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->pluck('id');

        /** @var array<string, int> $counts */
        $counts = Issue::withoutGlobalScopes()
            ->where('agency_id', $organization->agency_id)
            ->whereIn('property_id', $propertyIds)
            ->whereIn('status', array_map(fn (IssueStatus $s) => $s->value, IssueStatus::activeStatuses()))
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        return response()->json([
            'critical' => (int) ($counts[IssueSeverity::Critical->value] ?? 0),
            'high' => (int) ($counts[IssueSeverity::High->value] ?? 0),
            'medium' => (int) ($counts[IssueSeverity::Medium->value] ?? 0),
            'low' => (int) ($counts[IssueSeverity::Low->value] ?? 0),
            'total' => array_sum($counts),
            'generated_at' => now()->toISOString(),
        ]);
    }
}
