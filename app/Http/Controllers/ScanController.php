<?php

namespace App\Http\Controllers;

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
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScanController extends Controller
{
    use AuthorizesRequests;

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
        $this->authorize('create', Scan::class);

        $property = Property::findOrFail($request->validated()['property_id']);

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
                'performance_score',
                'accessibility_score',
                'best_practices_score',
                'seo_score',
                'largest_contentful_paint',
                'first_contentful_paint',
                'total_blocking_time',
                'cumulative_layout_shift',
            ])
            ->get();

        $delta = null;

        if ($scan->status === ScanStatus::Completed) {
            $metrics = ScanMetric::query()
                ->where('scan_id', $scan->id)
                ->whereIn('metric_name', ['new_issue_count', 'resolved_issue_count', 'risk_trend', 'lighthouse_accessibility_delta'])
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
                ];
            }
        }

        return Inertia::render('scans/show', [
            'scan' => $scan,
            'severityBreakdown' => $severityBreakdown,
            'topRules' => $topRules,
            'lighthouseResults' => $lighthouseResults,
            'delta' => $delta,
        ]);
    }
}
