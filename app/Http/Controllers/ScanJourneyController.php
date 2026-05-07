<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScanJourneyRequest;
use App\Http\Requests\UpdateScanJourneyRequest;
use App\Models\Agency;
use App\Models\ScanJourney;
use App\Models\ScanJourneyStep;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScanJourneyController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly Agency $agency) {}

    public function index(): Response
    {
        $this->authorize('viewAny', ScanJourney::class);

        $journeys = ScanJourney::query()
            ->with('property:id,name')
            ->withCount('steps')
            ->latest()
            ->get();

        return Inertia::render('journeys/index', [
            'journeys' => $journeys,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ScanJourney::class);

        $properties = $this->agency->properties()
            ->select(['id', 'name', 'organization_id'])
            ->orderBy('name')
            ->get();

        return Inertia::render('journeys/create', [
            'properties' => $properties,
        ]);
    }

    public function store(StoreScanJourneyRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $journey = ScanJourney::create([
                'agency_id' => $this->agency->id,
                'organization_id' => $this->agency->properties()->findOrFail($validated['property_id'])->organization_id,
                'property_id' => $validated['property_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            foreach ($validated['steps'] as $index => $step) {
                ScanJourneyStep::create([
                    'scan_journey_id' => $journey->id,
                    'position' => $index,
                    'label' => $step['label'],
                    'url' => $step['url'],
                ]);
            }
        });

        return redirect()->route('journeys.index');
    }

    public function edit(ScanJourney $scanJourney): Response
    {
        $this->authorize('update', $scanJourney);

        $properties = $this->agency->properties()
            ->select(['id', 'name', 'organization_id'])
            ->orderBy('name')
            ->get();

        return Inertia::render('journeys/edit', [
            'journey' => $scanJourney->load('steps'),
            'properties' => $properties,
        ]);
    }

    public function update(UpdateScanJourneyRequest $request, ScanJourney $scanJourney): RedirectResponse
    {
        $this->authorize('update', $scanJourney);

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $scanJourney): void {
            $scanJourney->update([
                'name' => $validated['name'] ?? $scanJourney->name,
                'description' => array_key_exists('description', $validated) ? $validated['description'] : $scanJourney->description,
            ]);

            if (isset($validated['steps'])) {
                $scanJourney->steps()->delete();

                foreach ($validated['steps'] as $index => $step) {
                    ScanJourneyStep::create([
                        'scan_journey_id' => $scanJourney->id,
                        'position' => $index,
                        'label' => $step['label'],
                        'url' => $step['url'],
                    ]);
                }
            }
        });

        return redirect()->route('journeys.index');
    }

    public function destroy(ScanJourney $scanJourney): RedirectResponse
    {
        $this->authorize('delete', $scanJourney);

        $scanJourney->delete();

        return redirect()->route('journeys.index');
    }
}
