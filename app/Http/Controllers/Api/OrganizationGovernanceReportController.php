<?php

namespace App\Http\Controllers\Api;

use App\Domain\Risk\GenerateGovernanceSummary;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationGovernanceReportController extends Controller
{
    public function __construct(private readonly GenerateGovernanceSummary $summary) {}

    public function __invoke(Request $request, int $organizationId): JsonResponse
    {
        $organization = Organization::withoutGlobalScopes()->findOrFail($organizationId);

        $user = $request->user();
        abort_unless($user->isSuperUser() || $user->canManageOrg($organization->id), 403);

        return response()->json($this->summary->handle($organization));
    }
}
