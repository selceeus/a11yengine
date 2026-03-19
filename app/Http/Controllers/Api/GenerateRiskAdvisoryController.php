<?php

namespace App\Http\Controllers\Api;

use App\Enums\RiskAdvisoryStatus;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateRiskAdvisoryJob;
use App\Models\Property;
use App\Models\RiskAdvisory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerateRiskAdvisoryController extends Controller
{
    public function __invoke(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $property->agency_id, 403);

        $advisory = RiskAdvisory::withoutGlobalScopes()->create([
            'agency_id' => $property->agency_id,
            'organization_id' => $property->organization_id,
            'property_id' => $property->id,
            'status' => RiskAdvisoryStatus::Pending,
        ]);

        GenerateRiskAdvisoryJob::dispatch($advisory);

        return response()->json([
            'id' => $advisory->id,
            'status' => $advisory->status->value,
        ], 202);
    }
}
