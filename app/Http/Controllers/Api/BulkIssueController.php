<?php

namespace App\Http\Controllers\Api;

use App\Enums\IssueActivityType;
use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdateIssueRequest;
use App\Models\Issue;
use App\Models\IssueActivity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BulkIssueController extends Controller
{
    public function update(BulkUpdateIssueRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = Auth::user();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Issue> $issues */
        $issues = Issue::query()
            ->whereIn('id', $data['ids'])
            ->get();

        $affected = 0;

        foreach ($issues as $issue) {
            match ($data['action']) {
                'status_change' => $this->applyStatusChange($issue, $data['status'], $user),
                'assign' => $this->applyAssign($issue, $data['user_id'] ?? null),
                'ignore' => $this->applyIgnore($issue, $user),
                'set_due_date' => $this->applyDueDate($issue, $data['due_date'] ?? null, $user),
                'delete' => $issue->delete(),
            };

            if ($data['action'] !== 'delete') {
                IssueActivity::create([
                    'issue_id' => $issue->id,
                    'user_id' => $user?->id,
                    'type' => IssueActivityType::BulkAction,
                    'metadata' => [
                        'action' => $data['action'],
                        'actor' => $user?->name,
                    ],
                    'created_at' => now(),
                ]);
            }

            $affected++;
        }

        return response()->json(['affected' => $affected]);
    }

    private function applyStatusChange(Issue $issue, string $status, ?User $user): void
    {
        $newStatus = IssueStatus::from($status);

        $issue->update([
            'status' => $newStatus,
            'resolved_at' => $newStatus->isTerminal() ? ($issue->resolved_at ?? now()) : null,
        ]);
    }

    private function applyAssign(Issue $issue, ?int $userId): void
    {
        if ($userId === null) {
            $issue->unassignUser();

            return;
        }

        $assignee = User::query()
            ->where('id', $userId)
            ->where('agency_id', $issue->agency_id)
            ->firstOrFail();

        $issue->assignToUser($assignee);
    }

    private function applyIgnore(Issue $issue, ?User $user): void
    {
        $issue->update([
            'status' => IssueStatus::Ignored,
            'resolved_at' => $issue->resolved_at ?? now(),
        ]);
    }

    private function applyDueDate(Issue $issue, ?string $date, ?User $user): void
    {
        $issue->update(['due_date' => $date]);
    }
}
