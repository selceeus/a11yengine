<?php

namespace App\Http\Controllers\Api;

use App\Enums\RiskAdvisoryStatus;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateRiskAdvisoryJob;
use App\Models\Property;
use App\Models\RiskAdvisory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerateRiskAdvisoryController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Property $property): JsonResponse
    {
        $this->authorize('create', [RiskAdvisory::class, $property]);

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
