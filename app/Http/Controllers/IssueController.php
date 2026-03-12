<?php

namespace App\Http\Controllers;

use App\Enums\IssueStatus;
use App\Http\Requests\UpdateIssueRequest;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly Agency $agency) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Issue::class);

        $issues = Issue::query()
            ->with(['property:id,name', 'organization:id,name'])
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(request('severity'), fn ($q, $severity) => $q->where('severity', $severity))
            ->when(request('property_id'), fn ($q, $propertyId) => $q->where('property_id', $propertyId))
            ->when(request('wcag_category'), fn ($q, $category) => $q->where('wcag_category', $category))
            ->when(request('date_from'), fn ($q, $date) => $q->whereDate('last_detected_at', '>=', $date))
            ->when(request('date_to'), fn ($q, $date) => $q->whereDate('last_detected_at', '<=', $date))
            ->latest('last_detected_at')
            ->paginate(50)
            ->withQueryString();

        $properties = $this->agency->properties()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return Inertia::render('issues/index', [
            'issues' => $issues,
            'filters' => request()->only(['status', 'severity', 'property_id', 'wcag_category', 'date_from', 'date_to']),
            'statuses' => IssueStatus::cases(),
            'properties' => $properties,
        ]);
    }

    public function show(Issue $issue): Response
    {
        $this->authorize('view', $issue);

        $issue->load([
            'property:id,name,base_url',
            'organization:id,name',
            'assignedUser:id,name,email',
            'findings' => fn ($q) => $q->latest('detected_at')->limit(50),
        ]);

        $assignableUsers = User::query()
            ->where('agency_id', $issue->agency_id)
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();

        return Inertia::render('issues/show', [
            'issue' => $issue,
            'assignableUsers' => $assignableUsers,
        ]);
    }

    public function update(UpdateIssueRequest $request, Issue $issue): RedirectResponse
    {
        $this->authorize('update', $issue);

        $newStatus = IssueStatus::from($request->validated()['status']);

        $issue->update([
            'status' => $newStatus,
            'resolved_at' => $newStatus->isTerminal() ? ($issue->resolved_at ?? now()) : null,
        ]);

        return redirect()->route('issues.show', $issue);
    }
}
