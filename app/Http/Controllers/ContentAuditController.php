<?php

namespace App\Http\Controllers;

use App\Concerns\Exportable;
use App\Models\ContentAudit;
use App\Models\Property;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentAuditController extends Controller
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

    public function show(ContentAudit $contentAudit): Response
    {
        $this->authorize('viewAny', Property::class);

        $contentAudit->load(['property:id,name,base_url', 'organization:id,name']);

        return Inertia::render('content-audit/show', [
            'audit' => [
                'id' => $contentAudit->id,
                'status' => $contentAudit->status->value,
                'content_issues' => $contentAudit->content_issues ?? [],
                'total_issues' => $contentAudit->total_issues,
                'pages_analyzed' => $contentAudit->pages_analyzed,
                'generated_at' => $contentAudit->generated_at?->toIso8601String(),
                'error_message' => $contentAudit->error_message,
                'property' => $contentAudit->property ? [
                    'id' => $contentAudit->property->id,
                    'name' => $contentAudit->property->name,
                    'base_url' => $contentAudit->property->base_url,
                ] : null,
                'organization' => $contentAudit->organization ? [
                    'id' => $contentAudit->organization->id,
                    'name' => $contentAudit->organization->name,
                ] : null,
            ],
        ]);
    }

    public function export(Request $request, ContentAudit $contentAudit, string $format): \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        $this->authorize('viewAny', Property::class);

        $contentAudit->load(['property:id,name,base_url']);

        $filename = 'content-audit-'.$contentAudit->id.'-'.now()->format('Y-m-d');

        return match ($format) {
            'json' => $this->exportJson([
                'id' => $contentAudit->id,
                'property' => $contentAudit->property?->name,
                'total_issues' => $contentAudit->total_issues,
                'pages_analyzed' => $contentAudit->pages_analyzed,
                'content_issues' => $contentAudit->content_issues ?? [],
                'generated_at' => $contentAudit->generated_at?->toIso8601String(),
            ], $filename.'.json'),
            'csv' => $this->exportCsv(
                collect($contentAudit->content_issues ?? [])->map(fn (array $issue) => [
                    $issue['issue_type'] ?? '',
                    $issue['severity'] ?? '',
                    $issue['page_url'] ?? '',
                    $issue['issue'] ?? '',
                    $issue['element_html'] ?? '',
                    $issue['suggestion'] ?? '',
                ])->all(),
                ['Type', 'Severity', 'Page URL', 'Description', 'Element', 'Recommendation'],
                $filename.'.csv'
            ),
            default => $this->exportPdf('content-audit.pdf', ['audit' => $contentAudit], $filename.'.html'),
        };
    }
}
