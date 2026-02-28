<?php

namespace App\Http\Controllers\Api;

use App\Domain\Risk\GenerateUserImpactReport;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class OrganizationUserImpactController extends Controller
{
    public function __construct(private readonly GenerateUserImpactReport $report) {}

    public function __invoke(int $organizationId): JsonResponse
    {
        $organization = Organization::withoutGlobalScopes()->findOrFail($organizationId);

        return response()->json($this->report->handle($organization));
    }
}
