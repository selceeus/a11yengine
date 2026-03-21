<?php

namespace App\Mcp\Tools;

use App\Domain\Issues\AiRemediationService;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Scopes\TenantScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Return the AI-generated remediation guidance for a specific issue. If remediation has not been generated yet it will be produced synchronously (may take a few seconds).')]
class GetIssueRemediationTool extends Tool
{
    public function __construct(
        private readonly Agency $agency,
        private readonly AiRemediationService $remediationService,
    ) {}

    public function handle(Request $request): Response
    {
        $request->validate([
            'issue_id' => ['required', 'integer'],
        ]);

        $issue = Issue::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $this->agency->id)
            ->find($request->get('issue_id'));

        if ($issue === null) {
            return Response::error('Issue not found.');
        }

        $suggestions = $this->remediationService->generate($issue);

        if (empty($suggestions)) {
            return Response::error('Remediation could not be generated for this issue.');
        }

        return Response::json([
            'issue_id' => $issue->id,
            'rule_key' => $issue->rule_key,
            'wcag_criteria' => $issue->wcag_criteria,
            'severity' => $issue->severity instanceof \App\Enums\IssueSeverity
                ? $issue->severity->value
                : (string) $issue->severity,
            'remediation' => $suggestions,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->integer()->description('The numeric ID of the issue to retrieve remediation for.')->required(),
        ];
    }
}
