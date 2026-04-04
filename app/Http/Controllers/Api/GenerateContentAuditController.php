<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContentAuditStatus;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateContentAuditJob;
use App\Models\ContentAudit;
use App\Models\Property;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerateContentAuditController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Property $property): JsonResponse
    {
        $this->authorize('create', [ContentAudit::class, $property]);

        $audit = ContentAudit::withoutGlobalScopes()->create([
            'agency_id' => $property->agency_id,
            'organization_id' => $property->organization_id,
            'property_id' => $property->id,
            'status' => ContentAuditStatus::Pending,
        ]);

        GenerateContentAuditJob::dispatch($audit);

        return response()->json([
            'id' => $audit->id,
            'status' => $audit->status->value,
        ], 202);
    }
}
