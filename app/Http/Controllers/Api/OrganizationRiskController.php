<?php

namespace App\Http\Controllers\Api;

use App\Domain\Issues\NormalizeScanFindings;
use App\Domain\Risk\RecordOrganizationRiskSnapshot;
use App\Domain\Risk\RecordRiskSnapshot;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Scan;
use Illuminate\Http\JsonResponse;

class OrganizationRiskController extends Controller
{
    public function __construct(
        private readonly NormalizeScanFindings $normalizer,
        private readonly RecordOrganizationRiskSnapshot $recorder,
        private readonly RecordRiskSnapshot $snapshotRecorder,
    ) {}

    public function __invoke(int $organizationId): JsonResponse
    {
        $organization = Organization::withoutGlobalScopes()->findOrFail($organizationId);

        $scans = Scan::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->get();

        foreach ($scans as $scan) {
            $this->normalizer->handle($scan);
        }

        $snapshot = $this->recorder->handle($organization);
        $this->snapshotRecorder->handle($organization);

        return response()->json([
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'scans_processed' => $scans->count(),
            'risk_score' => $snapshot->risk_score,
            'calculated_at' => $snapshot->calculated_at->toIso8601String(),
        ]);
    }
}
