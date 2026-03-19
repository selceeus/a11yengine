<?php

namespace App\Http\Controllers;

use App\Models\ContentAudit;
use App\Models\Property;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class ContentAuditController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', Property::class);

        $properties = Property::query()
            ->select(['id', 'name', 'base_url'])
            ->orderBy('name')
            ->get();

        $latestAudits = ContentAudit::query()
            ->whereIn('property_id', $properties->pluck('id'))
            ->latest()
            ->get()
            ->groupBy('property_id')
            ->map->first();

        return Inertia::render('content-audit/index', [
            'properties' => $properties->map(fn (Property $property) => [
                'id' => $property->id,
                'name' => $property->name,
                'base_url' => $property->base_url,
                'latestAudit' => $latestAudits->has($property->id)
                    ? [
                        'id' => $latestAudits->get($property->id)->id,
                        'status' => $latestAudits->get($property->id)->status->value,
                        'total_issues' => $latestAudits->get($property->id)->total_issues,
                        'pages_analyzed' => $latestAudits->get($property->id)->pages_analyzed,
                        'generated_at' => $latestAudits->get($property->id)->generated_at?->toIso8601String(),
                        'error_message' => $latestAudits->get($property->id)->error_message,
                    ]
                    : null,
            ]),
        ]);
    }
}
