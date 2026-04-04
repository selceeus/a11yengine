<?php

namespace App\Http\Controllers\Api;

use App\Enums\ClusterStatus;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateIssueClusteringJob;
use App\Models\IssueCluster;
use App\Models\Property;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerateIssueClustersController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Property $property): JsonResponse
    {
        $this->authorize('create', [IssueCluster::class, $property]);

        $cluster = IssueCluster::withoutGlobalScopes()->create([
            'agency_id' => $property->agency_id,
            'organization_id' => $property->organization_id,
            'property_id' => $property->id,
            'status' => ClusterStatus::Pending,
        ]);

        GenerateIssueClusteringJob::dispatch($cluster);

        return response()->json([
            'id' => $cluster->id,
            'status' => $cluster->status->value,
        ], 202);
    }
}
