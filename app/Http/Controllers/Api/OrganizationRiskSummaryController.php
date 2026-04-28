<?php

namespace App\Http\Controllers\Api;

use App\Domain\Risk\GetOrganizationRiskSummary;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\RiskSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationRiskSummaryController extends Controller
{
    public function __construct(private readonly GetOrganizationRiskSummary $summary) {}

    public function __invoke(Request $request, int $organizationId): JsonResponse
    {
        $organization = Organization::withoutGlobalScopes()->findOrFail($organizationId);

        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->canManageOrg($organization->id), 403);

        $summary = $this->summary->handle($organization);

        $snapshots = RiskSnapshot::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('snapshot_date')
            ->limit(30)
            ->get(['snapshot_date', 'total_risk_score', 'open_issue_count'])
            ->map(fn (RiskSnapshot $s) => [
                'snapshot_date' => $s->snapshot_date->toDateString(),
                'total_risk_score' => $s->total_risk_score,
                'open_issue_count' => $s->open_issue_count,
            ])
            ->values()
            ->all();

        return response()->json(array_merge($summary, [
            'snapshots' => $snapshots,
        ]));
    }
}
