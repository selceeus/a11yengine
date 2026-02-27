<?php

namespace App\Http\Controllers\Api;

use App\Domain\Risk\GetOrganizationGovernanceSummary;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class OrganizationGovernanceSummaryController extends Controller
{
    public function __construct(private readonly GetOrganizationGovernanceSummary $summary) {}

    public function __invoke(int $organizationId): JsonResponse
    {
        $organization = Organization::withoutGlobalScopes()->findOrFail($organizationId);

        return response()->json($this->summary->handle($organization));
    }
}
