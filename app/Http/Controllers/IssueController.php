<?php

namespace App\Http\Controllers;

use App\Enums\IssueStatus;
use App\Http\Requests\UpdateIssueRequest;
use App\Models\Issue;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', Issue::class);

        $issues = Issue::query()
            ->with([
                'property:id,name',
                'organization:id,name',
            ])
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(request('severity'), fn ($q, $severity) => $q->where('severity', $severity))
            ->when(request('property_id'), fn ($q, $propertyId) => $q->where('property_id', $propertyId))
            ->latest('last_detected_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('issues/index', [
            'issues' => $issues,
            'filters' => request()->only(['status', 'severity', 'property_id']),
            'statuses' => IssueStatus::cases(),
        ]);
    }

    public function show(Issue $issue): Response
    {
        $this->authorize('view', $issue);

        $issue->load([
            'property:id,name,base_url',
            'organization:id,name',
            'findings' => fn ($q) => $q->latest('detected_at')->limit(50),
        ]);

        return Inertia::render('issues/show', [
            'issue' => $issue,
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
