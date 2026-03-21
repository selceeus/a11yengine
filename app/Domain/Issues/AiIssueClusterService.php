<?php

namespace App\Domain\Issues;

use App\Ai\Agents\IssueClusterAgent;
use App\Enums\ClusterStatus;
use App\Enums\IssueStatus;
use App\Models\IssueCluster;
use Illuminate\Support\Facades\Date;

class AiIssueClusterService
{
    public function __construct() {}

    /**
     * Generate AI-powered issue clusters for the given IssueCluster record.
     * Loads all open issues for the property, sends them to the AI for clustering,
     * and persists the structured result back to the record.
     */
    public function generate(IssueCluster $issueCluster): void
    {
        $issues = \App\Models\Issue::withoutGlobalScopes()
            ->where('property_id', $issueCluster->property_id)
            ->whereIn('status', array_map(
                fn (IssueStatus $s) => $s->value,
                IssueStatus::activeStatuses(),
            ))
            ->orderByDesc('risk_weight')
            ->get(['id', 'rule_key', 'page_url', 'severity', 'wcag_category', 'wcag_criteria', 'description', 'occurrence_count', 'risk_weight', 'tags']);

        $propertyName = $issueCluster->property?->name ?? 'Unknown';
        $prompt = $this->buildPrompt($issues->toArray(), $propertyName);

        $response = IssueClusterAgent::make()->prompt($prompt);
        $result = json_decode($response->text, true) ?? [];
        $clusters = $result['clusters'] ?? [];

        $issueCluster->update([
            'clusters' => $clusters,
            'total_clusters' => count($clusters),
            'open_issues_analyzed' => $issues->count(),
            'prompt_context' => $prompt,
            'raw_ai_response' => $response->text,
            'status' => ClusterStatus::Completed,
            'generated_at' => Date::now(),
        ]);
    }

    /**
     * Build the structured clustering prompt.
     *
     * @param  array<int, array<string, mixed>>  $issues
     */
    public function buildPrompt(array $issues, string $propertyName): string
    {
        $issuesJson = json_encode($issues, JSON_PRETTY_PRINT);
        $count = count($issues);

        return <<<PROMPT
You are analyzing {$count} open accessibility issues for the website "{$propertyName}".

## Open Issues
{$issuesJson}

---

Group these issues into clusters based on shared root causes, affected components, or remediation patterns. For each cluster, identify the common template or component most likely causing the group of issues.

Respond with a single JSON object matching this exact schema (no prose, no markdown fences):

{
  "clusters": [
    {
      "cluster_name": "<concise name describing the root cause>",
      "common_component": "<template, component, or pattern causing these issues, or null if unknown>",
      "recommended_fix": "<specific, actionable batch fix description>",
      "severity": "critical|high|medium|low",
      "priority": "high|medium|low",
      "issue_ids": [<array of integer issue IDs from the input data>],
      "wcag_categories": [<array of WCAG criterion strings, e.g. ["1.1.1", "1.3.1"]>],
      "affected_pages": <integer count of unique pages affected>,
      "ai_notes": "<1-2 sentence note on cluster severity, systemic impact, and remediation priority>"
    }
  ]
}

Rules:
- Every issue ID must appear in exactly one cluster.
- Order clusters by priority descending (high first).
- Use only issue IDs present in the input data.
- common_component should be inferred from rule_key patterns, page_url paths, and tags.
- A cluster should have at least 2 issues; singletons may be grouped into a "Miscellaneous" cluster.
PROMPT;
    }
}
