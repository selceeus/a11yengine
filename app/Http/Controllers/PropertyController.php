<?php

namespace App\Http\Controllers;

use App\Enums\ScanStatus;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Models\Agency;
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

        $property->load('organization:id,name');

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

        return Inertia::render('properties/show', [
            'property' => $property,
            'recentScans' => $recentScans,
            'lighthouseAverages' => $lighthouseAverages,
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
