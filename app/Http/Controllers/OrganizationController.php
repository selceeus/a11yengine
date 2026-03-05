<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Agency;
use App\Models\Organization;
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
            ->orderBy('name')
            ->get();

        return Inertia::render('organizations/index', [
            'organizations' => $organizations,
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

        return Inertia::render('organizations/show', [
            'organization' => $organization,
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
