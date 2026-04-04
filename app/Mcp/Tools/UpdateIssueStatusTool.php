<?php

namespace App\Mcp\Tools;

use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Scopes\TenantScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update the status of an accessibility issue. Optionally provide resolution notes when marking as resolved, ignored, or false_positive.')]
class UpdateIssueStatusTool extends Tool
{
    public function __construct(private readonly Agency $agency) {}

    public function handle(Request $request): Response
    {
        $request->validate([
            'issue_id' => ['required', 'integer'],
            'status' => ['required', 'string', 'in:open,in_progress,resolved,ignored,false_positive'],
            'resolution_notes' => ['nullable', 'string'],
        ]);

        $issue = Issue::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $this->agency->id)
            ->find((int) $request->get('issue_id'));

        if ($issue === null) {
            return Response::error('Issue not found.');
        }

        $newStatus = IssueStatus::from((string) $request->get('status'));
        $previousStatus = $issue->status->value;

        $data = ['status' => $newStatus];

        if ($newStatus->isTerminal()) {
            $data['resolved_at'] = $issue->resolved_at ?? now();
        } else {
            $data['resolved_at'] = null;
        }

        if ($request->get('resolution_notes') !== null) {
            $data['resolution_notes'] = (string) $request->get('resolution_notes');
        }

        $issue->update($data);

        return Response::json([
            'issue_id' => $issue->id,
            'rule_key' => $issue->rule_key,
            'previous_status' => $previousStatus,
            'new_status' => $issue->status->value,
            'resolved_at' => $issue->resolved_at?->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->integer()->description('The ID of the issue to update.')->required(),
            'status' => $schema->string()->enum(['open', 'in_progress', 'resolved', 'ignored', 'false_positive'])->description('The new status for the issue.')->required(),
            'resolution_notes' => $schema->string()->description('Optional notes explaining the resolution or reason for the status change.'),
        ];
    }
}
