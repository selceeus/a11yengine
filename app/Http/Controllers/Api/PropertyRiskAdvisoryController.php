<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\RiskAdvisory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyRiskAdvisoryController extends Controller
{
    public function __invoke(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $property->agency_id, 403);

        $latest = RiskAdvisory::withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->latest()
            ->first();

        if ($latest === null) {
            return response()->json([
                'status' => null,
                'priorities' => [],
                'total_recommendations' => 0,
                'issues_analyzed' => 0,
                'generated_at' => null,
            ]);
        }

        return response()->json([
            'id' => $latest->id,
            'status' => $latest->status->value,
            'priorities' => $latest->priorities ?? [],
            'total_recommendations' => $latest->total_recommendations ?? 0,
            'issues_analyzed' => $latest->issues_analyzed ?? 0,
            'generated_at' => $latest->generated_at?->toIso8601String(),
            'error_message' => $latest->error_message,
        ]);
    }
}
