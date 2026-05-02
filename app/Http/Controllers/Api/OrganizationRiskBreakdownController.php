<?php

namespace App\Http\Controllers\Api;

use App\Domain\Risk\GenerateRiskBreakdown;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationRiskBreakdownController extends Controller
{
    public function __construct(private readonly GenerateRiskBreakdown $breakdown) {}

    public function __invoke(Request $request, int $organizationId): JsonResponse
    {
        $organization = Organization::withoutGlobalScopes()->findOrFail($organizationId);

        $user = $request->user();
        abort_unless($user->isSuperUser() || $user->canManageOrg($organization->id), 403);

        return response()->json($this->breakdown->handle($organization));
    }
}
