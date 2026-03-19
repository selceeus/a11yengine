<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GovernanceReport;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyGovernanceReportController extends Controller
{
    public function __invoke(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $property->agency_id, 403);

        $latest = GovernanceReport::withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->latest()
            ->first();

        if ($latest === null) {
            return response()->json([
                'status' => null,
                'report_scope' => 'property',
                'executive_narrative' => null,
                'summary_cards' => [],
                'risk_trend' => [],
                'severity_breakdown' => [],
                'remediation_progress' => [],
                'compliance_status' => [],
                'recommendations' => [],
                'generated_at' => null,
            ]);
        }

        return response()->json([
            'id' => $latest->id,
            'status' => $latest->status->value,
            'report_scope' => $latest->report_scope,
            'period_from' => $latest->period_from->toDateString(),
            'period_to' => $latest->period_to->toDateString(),
            'executive_narrative' => $latest->executive_narrative,
            'summary_cards' => $latest->summary_cards ?? [],
            'risk_trend' => $latest->risk_trend ?? [],
            'severity_breakdown' => $latest->severity_breakdown ?? [],
            'remediation_progress' => $latest->remediation_progress ?? [],
            'compliance_status' => $latest->compliance_status ?? [],
            'recommendations' => $latest->recommendations ?? [],
            'generated_at' => $latest->generated_at?->toIso8601String(),
            'error_message' => $latest->error_message,
        ]);
    }
}
