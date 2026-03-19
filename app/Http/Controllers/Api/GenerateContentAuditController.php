<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContentAuditStatus;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateContentAuditJob;
use App\Models\ContentAudit;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerateContentAuditController extends Controller
{
    public function __invoke(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $property->agency_id, 403);

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
