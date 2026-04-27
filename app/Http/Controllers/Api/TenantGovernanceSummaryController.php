<?php

namespace App\Http\Controllers\Api;

use App\Domain\Governance\AgencyGovernanceSummary;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\JsonResponse;

class TenantGovernanceSummaryController extends Controller
{
    public function __invoke(Agency $agency): JsonResponse
    {
        return response()->json(AgencyGovernanceSummary::forAgency($agency));
    }
}
