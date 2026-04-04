<?php

namespace App\Http\Controllers;

use App\Models\IssueCluster;
use App\Models\Property;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class IssueClusterController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', IssueCluster::class);

        $properties = Property::query()
            ->select(['id', 'name', 'base_url'])
            ->orderBy('name')
            ->get();

        $latestClusters = IssueCluster::query()
            ->whereIn('property_id', $properties->pluck('id'))
            ->latest()
            ->get()
            ->groupBy('property_id')
            ->map->first();

        return Inertia::render('issue-clusters/index', [
            'properties' => $properties->map(fn (Property $property) => [
                'id' => $property->id,
                'name' => $property->name,
                'base_url' => $property->base_url,
                'latestCluster' => $latestClusters->has($property->id)
                    ? [
                        'id' => $latestClusters->get($property->id)->id,
                        'status' => $latestClusters->get($property->id)->status->value,
                        'total_clusters' => $latestClusters->get($property->id)->total_clusters,
                        'open_issues_analyzed' => $latestClusters->get($property->id)->open_issues_analyzed,
                        'generated_at' => $latestClusters->get($property->id)->generated_at?->toIso8601String(),
                        'error_message' => $latestClusters->get($property->id)->error_message,
                    ]
                    : null,
            ]),
        ]);
    }
}
