<?php

namespace App\Http\Controllers\Api;

use App\Domain\Risk\GenerateUserImpactReport;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationUserImpactController extends Controller
{
    public function __construct(private readonly GenerateUserImpactReport $report) {}

    public function __invoke(Request $request, int $organizationId): JsonResponse
    {
        $organization = Organization::withoutGlobalScopes()->findOrFail($organizationId);

        $user = $request->user();
        abort_unless($user->isSuperUser() || $user->canManageOrg($organization->id), 403);

        return response()->json($this->report->handle($organization));
    }
}
