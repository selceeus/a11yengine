<?php

namespace App\Http\Controllers\Api;

use App\Domain\Risk\RecordOrganizationRiskSnapshot;
use App\Domain\Risk\RecordRiskSnapshot;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationRiskController extends Controller
{
    public function __construct(
        private readonly RecordOrganizationRiskSnapshot $recorder,
        private readonly RecordRiskSnapshot $snapshotRecorder,
    ) {}

    public function __invoke(Request $request, int $organizationId): JsonResponse
    {
        $organization = Organization::withoutGlobalScopes()->findOrFail($organizationId);

        $user = $request->user();
        abort_unless($user->isSuperUser() || $user->canManageOrg($organization->id), 403);

        $snapshot = $this->recorder->handle($organization);
        $this->snapshotRecorder->handle($organization);

        return response()->json([
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'risk_score' => $snapshot->risk_score,
            'calculated_at' => $snapshot->calculated_at->toIso8601String(),
        ]);
    }
}
