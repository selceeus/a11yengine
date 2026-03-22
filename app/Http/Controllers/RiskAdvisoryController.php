<?php

namespace App\Http\Controllers;

use App\Concerns\Exportable;
use App\Models\Property;
use App\Models\RiskAdvisory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RiskAdvisoryController extends Controller
{
    use AuthorizesRequests;
    use Exportable;

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

    public function show(RiskAdvisory $riskAdvisory): Response
    {
        $this->authorize('viewAny', Property::class);

        $riskAdvisory->load(['property:id,name,base_url', 'organization:id,name']);

        return Inertia::render('risk-advisory/show', [
            'advisory' => [
                'id' => $riskAdvisory->id,
                'status' => $riskAdvisory->status->value,
                'priorities' => $riskAdvisory->priorities ?? [],
                'total_recommendations' => $riskAdvisory->total_recommendations,
                'issues_analyzed' => $riskAdvisory->issues_analyzed,
                'generated_at' => $riskAdvisory->generated_at?->toIso8601String(),
                'error_message' => $riskAdvisory->error_message,
                'property' => $riskAdvisory->property ? [
                    'id' => $riskAdvisory->property->id,
                    'name' => $riskAdvisory->property->name,
                    'base_url' => $riskAdvisory->property->base_url,
                ] : null,
                'organization' => $riskAdvisory->organization ? [
                    'id' => $riskAdvisory->organization->id,
                    'name' => $riskAdvisory->organization->name,
                ] : null,
            ],
        ]);
    }

    public function export(Request $request, RiskAdvisory $riskAdvisory, string $format): \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        $this->authorize('viewAny', Property::class);

        $riskAdvisory->load(['property:id,name,base_url']);

        $filename = 'risk-advisory-'.$riskAdvisory->id.'-'.now()->format('Y-m-d');

        return match ($format) {
            'json' => $this->exportJson([
                'id' => $riskAdvisory->id,
                'property' => $riskAdvisory->property?->name,
                'total_recommendations' => $riskAdvisory->total_recommendations,
                'issues_analyzed' => $riskAdvisory->issues_analyzed,
                'priorities' => $riskAdvisory->priorities ?? [],
                'generated_at' => $riskAdvisory->generated_at?->toIso8601String(),
            ], $filename.'.json'),
            'csv' => $this->exportCsv(
                collect($riskAdvisory->priorities ?? [])->map(fn (array $p) => [
                    $p['rank'] ?? '',
                    $p['rule_key'] ?? '',
                    $p['severity'] ?? '',
                    $p['risk_reduction_score'] ?? '',
                    $p['rationale'] ?? '',
                    $p['recommended_action'] ?? '',
                ])->all(),
                ['Rank', 'Rule Key', 'Severity', 'Risk Reduction Score', 'Rationale', 'Recommended Action'],
                $filename.'.csv'
            ),
            default => $this->exportPdf('risk-advisory.pdf', ['advisory' => $riskAdvisory], $filename.'.html'),
        };
    }
}
