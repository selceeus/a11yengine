<?php

namespace App\Http\Controllers\Api;

use App\Domain\Governance\AgencyGovernanceSummary;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyGovernanceReportController extends Controller
{
    public function __invoke(Request $request, Agency $agency): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $agency->id, 403);

        return response()->json(AgencyGovernanceSummary::forAgency($agency));
    }
}
