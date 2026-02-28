<?php

namespace App\Http\Controllers\Api;

use App\Domain\Risk\GenerateGovernanceSummary;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class OrganizationGovernanceReportController extends Controller
{
    public function __construct(private readonly GenerateGovernanceSummary $summary) {}

    public function __invoke(int $organizationId): JsonResponse
    {
        $organization = Organization::withoutGlobalScopes()->findOrFail($organizationId);

        return response()->json($this->summary->handle($organization));
    }
}
