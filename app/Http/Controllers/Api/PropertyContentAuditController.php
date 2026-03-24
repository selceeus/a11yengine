<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentAudit;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyContentAuditController extends Controller
{
    public function __invoke(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $property->agency_id, 403);

        $latest = ContentAudit::withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->latest()
            ->first();

        if ($latest === null) {
            return response()->json([
                'status' => null,
                'content_issues' => [],
                'total_issues' => 0,
                'pages_analyzed' => 0,
                'reading_metrics' => [],
                'avg_reading_level' => null,
                'avg_reading_time_seconds' => null,
                'generated_at' => null,
            ]);
        }

        return response()->json([
            'id' => $latest->id,
            'status' => $latest->status->value,
            'content_issues' => $latest->content_issues ?? [],
            'total_issues' => $latest->total_issues ?? 0,
            'pages_analyzed' => $latest->pages_analyzed ?? 0,
            'reading_metrics' => $latest->reading_metrics ?? [],
            'avg_reading_level' => $latest->avg_reading_level,
            'avg_reading_time_seconds' => $latest->avg_reading_time_seconds,
            'generated_at' => $latest->generated_at?->toIso8601String(),
            'error_message' => $latest->error_message,
        ]);
    }
}
