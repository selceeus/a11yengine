<?php

namespace App\Http\Controllers;

use App\Enums\FindingSeverity;
use App\Enums\ScanStatus;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Models\Agency;
use App\Models\Finding;
use App\Models\LighthouseResult;
use App\Models\Property;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly Agency $agency) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Property::class);

        $properties = Property::query()
            ->with('organization:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('properties/index', [
            'properties' => $properties,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Property::class);

        $organizations = $this->agency->organizations()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return Inertia::render('properties/create', [
            'organizations' => $organizations,
        ]);
    }

    public function store(StorePropertyRequest $request): RedirectResponse
    {
        $this->authorize('create', Property::class);

        $property = $this->agency->properties()->create([
            'organization_id' => $request->validated()['organization_id'],
            'name' => $request->validated()['name'],
            'base_url' => $request->validated()['base_url'],
            'status' => 'active',
        ]);

        return redirect()->route('properties.show', $property)
            ->with('status', 'Property created.');
    }

    public function show(Property $property): Response
    {
        $this->authorize('view', $property);

        $property->load(['organization:id,name', 'scheduledScan']);

        $recentScans = $property->scans()
            ->latest()
            ->limit(10)
            ->get();

        $scanIds = $property->scans()
            ->where('status', ScanStatus::Completed)
            ->pluck('id');

        $lighthouseAverages = null;
        if ($scanIds->isNotEmpty()) {
            $row = LighthouseResult::query()
                ->whereIn('scan_id', $scanIds)
                ->selectRaw('
                    ROUND(AVG(performance_score)) as performance_score,
                    ROUND(AVG(accessibility_score)) as accessibility_score,
                    ROUND(AVG(best_practices_score)) as best_practices_score,
                    ROUND(AVG(seo_score)) as seo_score
                ')
                ->first();

            if ($row && $row->performance_score !== null) {
                $lighthouseAverages = [
                    'performance_score' => (int) $row->performance_score,
                    'accessibility_score' => (int) $row->accessibility_score,
                    'best_practices_score' => (int) $row->best_practices_score,
                    'seo_score' => (int) $row->seo_score,
                ];
            }
        }

        $severityBreakdown = [];
        $topRules = [];

        if ($scanIds->isNotEmpty()) {
            $severities = Finding::query()
                ->whereIn('scan_id', $scanIds)
                ->selectRaw('severity, COUNT(*) as count')
                ->groupBy('severity')
                ->get();

            $order = array_flip(array_map(
                fn (FindingSeverity $s) => $s->value,
                FindingSeverity::cases(),
            ));

            $severityBreakdown = $severities
                ->sortBy(fn ($row) => $order[$row->severity->value] ?? 99)
                ->map(fn ($row) => [
                    'severity' => $row->severity->value,
                    'count' => (int) $row->count,
                ])
                ->values()
                ->toArray();

            $topRules = Finding::query()
                ->whereIn('scan_id', $scanIds)
                ->selectRaw('rule_key, COUNT(*) as count')
                ->groupBy('rule_key')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'rule_key')
                ->map(fn ($c) => (int) $c)
                ->toArray();
        }

        return Inertia::render('properties/show', [
            'property' => $property,
            'recentScans' => $recentScans,
            'lighthouseAverages' => $lighthouseAverages,
            'severityBreakdown' => $severityBreakdown,
            'topRules' => $topRules,
            'scheduledScan' => $property->scheduledScan ? [
                'id' => $property->scheduledScan->id,
                'type' => $property->scheduledScan->type,
                'frequency' => $property->scheduledScan->frequency,
                'scheduled_at' => $property->scheduledScan->scheduled_at?->toIso8601String(),
                'next_run_at' => $property->scheduledScan->next_run_at->toIso8601String(),
                'run_time' => $property->scheduledScan->run_time,
                'run_day_of_week' => $property->scheduledScan->run_day_of_week,
                'run_day_of_month' => $property->scheduledScan->run_day_of_month,
                'is_active' => $property->scheduledScan->is_active,
                'last_run_at' => $property->scheduledScan->last_run_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function edit(Property $property): Response
    {
        $this->authorize('update', $property);

        $property->load('organization:id,name');

        $organizations = $this->agency->organizations()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return Inertia::render('properties/edit', [
            'property' => $property,
            'organizations' => $organizations,
        ]);
    }

    public function update(UpdatePropertyRequest $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        $property->update($request->validated());

        return redirect()->route('properties.show', $property)
            ->with('status', 'Property updated.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        $this->authorize('delete', $property);

        $property->delete();

        return redirect()->route('properties.index')
            ->with('status', 'Property deleted.');
    }
}
