<?php

namespace App\Http\Controllers;

use App\Domain\Issues\AiRemediationService;
use App\Enums\IssueStatus;
use App\Http\Requests\UpdateIssueRequest;
use App\Jobs\GenerateIssueRemediationJob;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly Agency $agency,
        private readonly AiRemediationService $remediationService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Issue::class);

        $allowedSorts = ['severity', 'last_detected_at', 'due_date', 'occurrence_count'];
        $sort = in_array(request('sort'), $allowedSorts) ? request('sort') : 'severity';
        $direction = request('direction') === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) request('per_page'), [25, 50, 100]) ? (int) request('per_page') : 25;

        $baseQuery = Issue::query()
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(request('severity'), fn ($q, $severity) => $q->where('severity', $severity))
            ->when(request('property_id'), fn ($q, $propertyId) => $q->where('property_id', $propertyId))
            ->when(request('wcag_category'), fn ($q, $category) => $q->where('wcag_category', $category))
            ->when(request('date_from'), fn ($q, $date) => $q->whereDate('last_detected_at', '>=', $date))
            ->when(request('date_to'), fn ($q, $date) => $q->whereDate('last_detected_at', '<=', $date))
            ->when(request('assigned_user_id'), fn ($q, $userId) => $q->where('assigned_user_id', $userId))
            ->when(request('search'), fn ($q, $s) => $q->where(fn ($sub) => $sub
                ->where('description', 'like', '%'.$s.'%')
                ->orWhere('rule_key', 'like', '%'.$s.'%')
                ->orWhere('wcag_criteria', 'like', '%'.$s.'%')
                ->orWhere('page_url', 'like', '%'.$s.'%')
            ));

        // Summary counts scoped only to property_id (not other filters)
        $summary = Issue::query()
            ->when(request('property_id'), fn ($q, $propertyId) => $q->where('property_id', $propertyId))
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN severity = 'critical' AND status NOT IN ('resolved', 'ignored', 'false_positive') THEN 1 ELSE 0 END) as critical,
                SUM(CASE WHEN due_date IS NOT NULL AND due_date < NOW() AND status NOT IN ('resolved', 'ignored', 'false_positive') THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN assigned_user_id IS NULL AND status NOT IN ('resolved', 'ignored', 'false_positive') THEN 1 ELSE 0 END) as unassigned
            ")
            ->first();

        $issues = (clone $baseQuery)
            ->with(['property:id,name', 'organization:id,name', 'assignedUser:id,name'])
            ->when(
                $sort === 'severity',
                fn ($q) => $q->orderByRaw("CASE severity WHEN 'low' THEN 1 WHEN 'medium' THEN 2 WHEN 'high' THEN 3 WHEN 'critical' THEN 4 ELSE 0 END ".strtoupper($direction)),
                fn ($q) => $q->orderBy($sort, $direction),
            )
            ->paginate($perPage)
            ->withQueryString();

        $properties = $this->agency->properties()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $teamMembers = $this->agency->users()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return Inertia::render('issues/index', [
            'issues' => $issues,
            'summary' => [
                'total' => (int) $summary->total,
                'critical' => (int) $summary->critical,
                'overdue' => (int) $summary->overdue,
                'unassigned' => (int) $summary->unassigned,
            ],
            'filters' => array_merge(
                request()->only(['status', 'severity', 'property_id', 'wcag_category', 'date_from', 'date_to', 'assigned_user_id', 'search']),
                ['sort' => $sort, 'direction' => $direction, 'per_page' => $perPage],
            ),
            'statuses' => IssueStatus::cases(),
            'properties' => $properties,
            'teamMembers' => $teamMembers,
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
            'activities' => fn ($q) => $q->latest('created_at')->limit(50)->with('user:id,name'),
        ]);

        $assignableUsers = User::query()
            ->where('agency_id', $issue->agency_id)
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();

        return Inertia::render('issues/show', [
            'issue' => $issue,
            'assignableUsers' => $assignableUsers,
            'teamMembers' => $assignableUsers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
        ]);
    }

    public function update(UpdateIssueRequest $request, Issue $issue): RedirectResponse
    {
        $this->authorize('update', $issue);

        $validated = $request->validated();
        $updateData = [];

        if (isset($validated['status'])) {
            $newStatus = IssueStatus::from($validated['status']);
            $updateData['status'] = $newStatus;
            $updateData['resolved_at'] = $newStatus->isTerminal() ? ($issue->resolved_at ?? now()) : null;
        }

        if (array_key_exists('due_date', $validated)) {
            $updateData['due_date'] = $validated['due_date'];
        }

        $issue->update($updateData);

        return redirect()->route('issues.show', $issue);
    }

    public function generateRemediation(Issue $issue): RedirectResponse
    {
        $this->authorize('update', $issue);

        Cache::forget($this->remediationService->cacheKey($issue));

        $issue->update(['ai_remediation_status' => 'pending']);

        dispatch(new GenerateIssueRemediationJob($issue));

        return redirect()->route('issues.show', $issue);
    }
}
