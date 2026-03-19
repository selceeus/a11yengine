<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IssueCluster;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyIssueClustersController extends Controller
{
    public function __invoke(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $property->agency_id, 403);

        $latest = IssueCluster::withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->latest()
            ->first();

        if ($latest === null) {
            return response()->json([
                'status' => null,
                'clusters' => [],
                'total_clusters' => 0,
                'open_issues_analyzed' => 0,
                'generated_at' => null,
            ]);
        }

        return response()->json([
            'id' => $latest->id,
            'status' => $latest->status->value,
            'clusters' => $latest->clusters ?? [],
            'total_clusters' => $latest->total_clusters ?? 0,
            'open_issues_analyzed' => $latest->open_issues_analyzed ?? 0,
            'generated_at' => $latest->generated_at?->toIso8601String(),
            'error_message' => $latest->error_message,
        ]);
    }
}
