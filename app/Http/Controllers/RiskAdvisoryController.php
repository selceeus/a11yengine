<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\RiskAdvisory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class RiskAdvisoryController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', Property::class);

        $properties = Property::query()
            ->select(['id', 'name', 'base_url'])
            ->orderBy('name')
            ->get();

        $latestAdvisories = RiskAdvisory::query()
            ->whereIn('property_id', $properties->pluck('id'))
            ->latest()
            ->get()
            ->groupBy('property_id')
            ->map->first();

        return Inertia::render('risk-advisory/index', [
            'properties' => $properties->map(fn (Property $property) => [
                'id' => $property->id,
                'name' => $property->name,
                'base_url' => $property->base_url,
                'latestAdvisory' => $latestAdvisories->has($property->id)
                    ? [
                        'id' => $latestAdvisories->get($property->id)->id,
                        'status' => $latestAdvisories->get($property->id)->status->value,
                        'total_recommendations' => $latestAdvisories->get($property->id)->total_recommendations,
                        'issues_analyzed' => $latestAdvisories->get($property->id)->issues_analyzed,
                        'generated_at' => $latestAdvisories->get($property->id)->generated_at?->toIso8601String(),
                        'error_message' => $latestAdvisories->get($property->id)->error_message,
                    ]
                    : null,
            ]),
        ]);
    }
}
