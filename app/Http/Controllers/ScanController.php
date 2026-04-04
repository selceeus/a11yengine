<?php

namespace App\Http\Controllers;

use App\Concerns\Exportable;
use App\Domain\Scans\ScanConfig;
use App\Enums\ScanStatus;
use App\Http\Requests\StoreScanRequest;
use App\Jobs\RunScanJob;
use App\Models\Agency;
use App\Models\Finding;
use App\Models\LighthouseResult;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanMetric;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScanController extends Controller
{
    use AuthorizesRequests;
    use Exportable;

    public function __construct(private readonly Agency $agency) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Scan::class);

        $scans = Scan::query()
            ->with('property:id,name,base_url')
            ->latest()
            ->get();

        $properties = $this->agency->properties()
            ->select(['id', 'name', 'base_url', 'organization_id'])
            ->orderBy('name')
            ->get();

        return Inertia::render('scans/index', [
            'scans' => $scans,
            'properties' => $properties,
        ]);
    }

    public function store(StoreScanRequest $request): RedirectResponse
    {
        $property = Property::findOrFail($request->validated()['property_id']);

        $this->authorize('create', [Scan::class, $property]);

        $propertyConfig = $property->defaultScanConfig();
        $requestConfig = isset($request->validated()['scan_config'])
            ? ScanConfig::fromArray($request->validated()['scan_config'])
            : new ScanConfig;
        $resolvedConfig = $propertyConfig->merge($requestConfig);

        $scan = Scan::create([
            'agency_id' => $this->agency->id,
            'organization_id' => $property->organization_id,
            'property_id' => $property->id,
            'status' => ScanStatus::Pending,
            'scan_config' => $resolvedConfig->toArray(),
        ]);

        RunScanJob::dispatch($scan);

        return redirect()->route('scans.show', $scan);
    }

    public function show(Scan $scan): Response
    {
        $this->authorize('view', $scan);

        $scan->load([
            'property:id,name,base_url',
            'scanPages' => fn ($q) => $q->orderByDesc('violations_count'),
        ]);

        $severityBreakdown = Finding::query()
            ->where('scan_id', $scan->id)
            ->select('severity', DB::raw('count(*) as count'))
            ->groupBy('severity')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'severity' => $row->severity->value,
                'count' => $row->count,
            ]);

        $topRules = Finding::query()
            ->where('scan_id', $scan->id)
            ->select('rule_key', DB::raw('count(*) as count'))
            ->groupBy('rule_key')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'rule_key');

        $lighthouseResults = LighthouseResult::query()
            ->where('scan_id', $scan->id)
            ->select([
                'url',
                'form_factor',
                'performance_score',
                'accessibility_score',
                'best_practices_score',
                'seo_score',
                'largest_contentful_paint',
                'first_contentful_paint',
                'total_blocking_time',
                'cumulative_layout_shift',
            ])
            ->orderBy('url')
            ->orderBy('form_factor')
            ->get();

        $delta = null;
        $experiencePillars = null;

        if ($scan->status === ScanStatus::Completed) {
            $metrics = ScanMetric::query()
                ->where('scan_id', $scan->id)
                ->whereIn('metric_name', [
                    'new_issue_count',
                    'resolved_issue_count',
                    'risk_trend',
                    'lighthouse_accessibility_delta',
                    'experience_score',
                    'experience_score_delta',
                    'accessibility_risk_score',
                    'lighthouse_performance_avg',
                    'lighthouse_best_practices_avg',
                    'lighthouse_seo_avg',
                ])
                ->whereNull('page_id')
                ->pluck('metric_value', 'metric_name');

            if ($metrics->has('new_issue_count') || $metrics->has('resolved_issue_count')) {
                $delta = [
                    'new_count' => (int) ($metrics['new_issue_count'] ?? 0),
                    'resolved_count' => (int) ($metrics['resolved_issue_count'] ?? 0),
                    'risk_trend' => isset($metrics['risk_trend']) ? (float) $metrics['risk_trend'] : null,
                    'lighthouse_accessibility_delta' => isset($metrics['lighthouse_accessibility_delta'])
                        ? (float) $metrics['lighthouse_accessibility_delta']
                        : null,
                    'experience_score_delta' => isset($metrics['experience_score_delta'])
                        ? (float) $metrics['experience_score_delta']
                        : null,
                ];
            }

            if ($metrics->has('experience_score')) {
                $experiencePillars = [
                    'experience_score' => (float) $metrics['experience_score'],
                    'accessibility_score' => isset($metrics['accessibility_risk_score'])
                        ? (float) $metrics['accessibility_risk_score']
                        : null,
                    'performance_score' => isset($metrics['lighthouse_performance_avg'])
                        ? (float) $metrics['lighthouse_performance_avg']
                        : null,
                    'best_practices_score' => isset($metrics['lighthouse_best_practices_avg'])
                        ? (float) $metrics['lighthouse_best_practices_avg']
                        : null,
                    'seo_score' => isset($metrics['lighthouse_seo_avg'])
                        ? (float) $metrics['lighthouse_seo_avg']
                        : null,
                ];
            }
        }

        return Inertia::render('scans/show', [
            'scan' => $scan,
            'severityBreakdown' => $severityBreakdown,
            'topRules' => $topRules,
            'lighthouseResults' => $lighthouseResults,
            'delta' => $delta,
            'experiencePillars' => $experiencePillars,
        ]);
    }

    public function export(Request $request, Scan $scan, string $format): \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('view', $scan);

        $filename = 'scan-'.$scan->id.'-'.now()->format('Y-m-d');

        $findings = Finding::withoutGlobalScopes()
            ->where('scan_id', $scan->id)
            ->select(['rule_key', 'severity', 'page_url', 'element_identifier', 'message', 'wcag_criteria', 'detected_at'])
            ->get();

        $lighthouseResults = LighthouseResult::withoutGlobalScopes()
            ->where('scan_id', $scan->id)
            ->select(['url', 'form_factor', 'performance_score', 'accessibility_score', 'best_practices_score', 'seo_score', 'first_contentful_paint', 'largest_contentful_paint', 'total_blocking_time', 'cumulative_layout_shift'])
            ->orderBy('url')
            ->orderBy('form_factor')
            ->get();

        if ($format === 'csv') {
            $rows = $findings->map(fn (Finding $f) => [
                $f->rule_key,
                $f->severity->value,
                $f->page_url,
                $f->element_identifier,
                $f->message,
                $f->wcag_criteria,
                $f->detected_at?->toIso8601String(),
            ])->all();

            return $this->exportCsv(
                $rows,
                ['Rule Key', 'Severity', 'Page URL', 'Element', 'Message', 'WCAG Criteria', 'Detected At'],
                $filename.'.csv'
            );
        }

        return $this->exportJson([
            'scan_id' => $scan->id,
            'property' => $scan->property?->name,
            'status' => $scan->status->value,
            'findings' => $findings->map(fn (Finding $f) => [
                'rule_key' => $f->rule_key,
                'severity' => $f->severity->value,
                'page_url' => $f->page_url,
                'element_identifier' => $f->element_identifier,
                'message' => $f->message,
                'wcag_criteria' => $f->wcag_criteria,
                'detected_at' => $f->detected_at?->toIso8601String(),
            ])->all(),
            'lighthouse' => $lighthouseResults->map(fn (LighthouseResult $l) => [
                'url' => $l->url,
                'form_factor' => $l->form_factor,
                'performance_score' => $l->performance_score,
                'accessibility_score' => $l->accessibility_score,
                'best_practices_score' => $l->best_practices_score,
                'seo_score' => $l->seo_score,
                'first_contentful_paint' => $l->first_contentful_paint,
                'largest_contentful_paint' => $l->largest_contentful_paint,
                'total_blocking_time' => $l->total_blocking_time,
                'cumulative_layout_shift' => $l->cumulative_layout_shift,
            ])->all(),
        ], $filename.'.json');
    }
}
