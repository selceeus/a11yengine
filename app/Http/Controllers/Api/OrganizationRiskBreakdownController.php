<?php

namespace App\Http\Controllers\Api;

use App\Domain\Risk\GenerateRiskBreakdown;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class OrganizationRiskBreakdownController extends Controller
{
    public function __construct(private readonly GenerateRiskBreakdown $breakdown) {}

    public function __invoke(int $organizationId): JsonResponse
    {
        $organization = Organization::withoutGlobalScopes()->findOrFail($organizationId);

        return response()->json($this->breakdown->handle($organization));
    }
}
