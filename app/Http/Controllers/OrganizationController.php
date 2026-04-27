<?php

namespace App\Http\Controllers;

use App\Enums\IssueStatus;
use App\Enums\ScanStatus;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\RiskSnapshot;
use App\Models\Scan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly Agency $agency) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Organization::class);

        $organizations = Organization::query()
            ->withCount('properties')
            ->when(request('search'), fn ($q, $s) => $q->where(fn ($sub) => $sub
                ->where('name', 'like', '%'.$s.'%')
                ->orWhere('domain', 'like', '%'.$s.'%')
            ))
            ->orderBy('name')
            ->get();

        return Inertia::render('organizations/index', [
            'organizations' => $organizations,
            'filters' => request()->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Organization::class);

        return Inertia::render('organizations/create');
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $this->authorize('create', Organization::class);

        $organization = $this->agency->organizations()->create([
            'name' => $request->validated()['name'],
            'domain' => $request->validated()['domain'] ?? null,
            'status' => 'active',
        ]);

        return redirect()->route('organizations.show', $organization);
    }

    public function show(Organization $organization): Response
    {
        $this->authorize('view', $organization);

        $organization->load(['properties' => fn ($q) => $q->orderBy('name')]);

        $propertyIds = $organization->properties->pluck('id');

        $openIssueCount = Issue::withoutGlobalScopes()
            ->where('agency_id', $organization->agency_id)
            ->whereIn('property_id', $propertyIds)
            ->whereIn('status', IssueStatus::activeStatusValues())
            ->count();

        $latestScan = Scan::withoutGlobalScopes()
            ->where('agency_id', $organization->agency_id)
            ->whereIn('property_id', $propertyIds)
            ->where('status', ScanStatus::Completed)
            ->latest('completed_at')
            ->first();

        $recentScans = Scan::withoutGlobalScopes()
            ->where('agency_id', $organization->agency_id)
            ->whereIn('property_id', $propertyIds)
            ->with('property:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Scan $s) => [
                'id' => $s->id,
                'status' => $s->status->value,
                'property_name' => $s->property?->name,
                'completed_at' => $s->completed_at?->toIso8601String(),
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        $latestSnapshot = RiskSnapshot::query()
            ->where('organization_id', $organization->id)
            ->latest('snapshot_date')
            ->first();

        return Inertia::render('organizations/show', [
            'organization' => $organization,
            'stats' => [
                'open_issue_count' => $openIssueCount,
                'latest_scan_at' => $latestScan?->completed_at?->toIso8601String(),
                'risk_score' => $latestSnapshot?->total_risk_score ?? 0,
            ],
            'recentScans' => $recentScans,
        ]);
    }

    public function edit(Organization $organization): Response
    {
        $this->authorize('update', $organization);

        return Inertia::render('organizations/edit', [
            'organization' => $organization,
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);

        $organization->update($request->validated());

        return redirect()->route('organizations.show', $organization);
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return redirect()->route('organizations.index');
    }
}
