<?php

namespace App\Http\Controllers\Api;

use App\Actions\Governance\CreateGovernanceReport;
use App\Http\Controllers\Controller;
use App\Models\GovernanceReport;
use App\Models\Property;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerateGovernanceReportController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Property $property, CreateGovernanceReport $action): JsonResponse
    {
        $this->authorize('create', [GovernanceReport::class, $property]);

        $validated = $request->validate([
            'period_from' => ['required', 'date', 'before:period_to'],
            'period_to' => ['required', 'date', 'after:period_from'],
        ]);

        $report = $action->handle([
            'agency_id' => $property->agency_id,
            'organization_id' => $property->organization_id,
            'property_id' => $property->id,
            'report_scope' => 'property',
            'period_from' => $validated['period_from'],
            'period_to' => $validated['period_to'],
        ]);

        return response()->json([
            'id' => $report->id,
            'status' => $report->status->value,
        ], 202);
    }
}
