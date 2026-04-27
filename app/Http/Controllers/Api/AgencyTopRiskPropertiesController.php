<?php

namespace App\Http\Controllers\Api;

use App\Domain\Risk\RiskTrendSpine;
use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyTopRiskPropertiesController extends Controller
{
    private const LIMIT = 10;

    public function __invoke(Request $request, Agency $agency): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $agency->id, 403);

        $activeStatuses = IssueStatus::activeStatusValues();

        $properties = Property::withoutGlobalScopes()
            ->where('properties.agency_id', $agency->id)
            ->with('organization:id,name')
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
                'property_id, '.RiskTrendSpine::severityCaseSql()
            )
            ->groupBy('property_id')
            ->get()
            ->pluck('severity_order', 'property_id')
            ->map(fn ($order) => RiskTrendSpine::rankToSeverity((int) $order));

        $result = $properties->map(fn (Property $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'organization_id' => $p->organization_id,
            'organization_name' => $p->organization?->name,
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
