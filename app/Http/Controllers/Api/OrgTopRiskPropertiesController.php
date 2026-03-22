<?php

namespace App\Http\Controllers\Api;

use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgTopRiskPropertiesController extends Controller
{
    private const LIMIT = 10;

    public function __invoke(Request $request, Organization $organization): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $organization->agency_id, 403);

        $activeStatuses = array_map(
            fn (IssueStatus $s) => $s->value,
            IssueStatus::activeStatuses()
        );

        $properties = Property::withoutGlobalScopes()
            ->where('properties.organization_id', $organization->id)
            ->withCount([
                'issues as open_issue_count' => fn ($q) => $q
                    ->withoutGlobalScopes()
                    ->whereIn('status', $activeStatuses),
            ])
            ->withSum([
                'issues as risk_score' => fn ($q) => $q
                    ->withoutGlobalScopes()
                    ->whereIn('status', $activeStatuses),
            ], 'risk_weight')
            ->orderByDesc('risk_score')
            ->limit(self::LIMIT)
            ->get();

        $propertyIds = $properties->pluck('id');

        $highestSeverities = Issue::withoutGlobalScopes()
            ->whereIn('property_id', $propertyIds)
            ->whereIn('status', $activeStatuses)
            ->selectRaw(
                "property_id, MAX(CASE severity
                    WHEN 'critical' THEN 4
                    WHEN 'high'     THEN 3
                    WHEN 'medium'   THEN 2
                    WHEN 'low'      THEN 1
                    ELSE 0 END) as severity_order"
            )
            ->groupBy('property_id')
            ->get()
            ->pluck('severity_order', 'property_id')
            ->map(fn ($order) => match ((int) $order) {
                4 => 'critical',
                3 => 'high',
                2 => 'medium',
                1 => 'low',
                default => null,
            });

        $result = $properties->map(fn (Property $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'risk_score' => (int) ($p->risk_score ?? 0),
            'open_issue_count' => (int) ($p->open_issue_count ?? 0),
            'highest_severity' => $highestSeverities[$p->id] ?? null,
        ]);

        return response()->json([
            'properties' => $result->values(),
            'generated_at' => now()->toISOString(),
        ]);
    }
}
