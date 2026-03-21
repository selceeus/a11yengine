<?php

namespace App\Domain\Risk;

use App\Ai\Agents\RiskAdvisoryAgent;
use App\Enums\IssueStatus;
use App\Enums\RiskAdvisoryStatus;
use App\Models\RiskAdvisory;
use App\Models\Scan;
use App\Models\ScanMetric;
use Illuminate\Support\Facades\Date;

class AiRiskAdvisorService
{
    public function __construct() {}

    /**
     * Generate AI-powered risk priorities for the given RiskAdvisory record.
     * Loads all open issues, computes traffic scores, and asks the AI to rank
     * them by risk-reduction potential.
     */
    public function generate(RiskAdvisory $advisory): void
    {
        $issues = \App\Models\Issue::withoutGlobalScopes()
            ->where('property_id', $advisory->property_id)
            ->whereIn('status', array_map(
                fn (\App\Enums\IssueStatus $s) => $s->value,
                IssueStatus::activeStatuses(),
            ))
            ->orderByDesc('risk_weight')
            ->limit(100)
            ->get(['id', 'rule_key', 'page_url', 'severity', 'wcag_category', 'wcag_criteria', 'description', 'occurrence_count', 'risk_weight', 'tags', 'first_detected_at']);

        $issuesWithTrafficScore = $issues->map(function (\App\Models\Issue $issue): array {
            $data = $issue->toArray();
            $data['traffic_score'] = round((float) $issue->occurrence_count * (float) $issue->risk_weight, 4);

            return $data;
        })->all();

        $propertyRiskScore = $this->resolvePropertyRiskScore($advisory->property_id);
        $propertyName = $advisory->property?->name ?? 'Unknown';

        $prompt = $this->buildPrompt($issuesWithTrafficScore, $propertyRiskScore, $propertyName);

        $response = RiskAdvisoryAgent::make()->prompt($prompt);
        $result = json_decode($response->text, true) ?? [];
        $priorities = $result['priorities'] ?? [];

        $advisory->update([
            'priorities' => $priorities,
            'total_recommendations' => count($priorities),
            'issues_analyzed' => $issues->count(),
            'prompt_context' => $prompt,
            'raw_ai_response' => $response->text,
            'status' => RiskAdvisoryStatus::Completed,
            'generated_at' => Date::now(),
        ]);
    }

    /**
     * Build the risk prioritisation prompt.
     *
     * @param  array<int, array<string, mixed>>  $issues
     */
    public function buildPrompt(array $issues, ?float $propertyRiskScore, string $propertyName): string
    {
        $issuesJson = json_encode($issues, JSON_PRETTY_PRINT);
        $count = count($issues);
        $riskContext = $propertyRiskScore !== null
            ? "The property currently has an overall accessibility risk score of {$propertyRiskScore}/100 (lower is riskier)."
            : 'No overall risk score is currently available for this property.';

        return <<<PROMPT
You are analysing {$count} open accessibility issues for the website "{$propertyName}".

## Context
{$riskContext}

Each issue below includes a `traffic_score` field computed as `occurrence_count × risk_weight`. A higher `traffic_score` indicates the issue appears on high-traffic or high-severity pages and affects more users — weight this heavily when ranking.

## Open Issues
{$issuesJson}

---

Rank these issues by their potential to reduce accessibility risk for the most users. Prioritise issues where:
- `traffic_score` is high (affects many users across many page occurrences)
- `severity` is critical or serious
- `ease_of_remediation` is easy (quick wins)
- Fixing the issue would have broad compliance importance

Return the top 20 highest-impact issues (or all, if fewer than 20) as a single JSON object matching this exact schema (no prose, no markdown fences):

{
  "priorities": [
    {
      "rank": <integer starting at 1>,
      "issue_id": <integer matching the `id` field from the input>,
      "title": "<concise, human-readable title for this issue>",
      "rule_key": "<rule_key from the input>",
      "severity": "critical|serious|moderate|minor",
      "risk_reduction_score": <integer 0-100 indicating how much fixing this issue reduces overall risk>,
      "ease_of_remediation": "easy|moderate|complex",
      "user_impact": "high|medium|low",
      "compliance_importance": "high|medium|low",
      "affected_pages": <integer count of unique pages affected>,
      "affected_page_urls": [<array of page_url strings from the input data for this issue>],
      "quick_win": <boolean — true if ease_of_remediation is easy AND risk_reduction_score >= 60>,
      "rationale": "<1-2 sentence explanation of why this issue is ranked here>"
    }
  ]
}

Rules:
- Order items by rank ascending (1 = highest priority).
- Use only issue IDs present in the input data.
- `affected_page_urls` should be an array of the `page_url` values from matching issues in the input.
- Do not include issues with severity "info".
PROMPT;
    }

    /**
     * Get the most recent `accessibility_risk_score` metric for the given property.
     */
    private function resolvePropertyRiskScore(int $propertyId): ?float
    {
        $latestScanId = Scan::withoutGlobalScopes()
            ->where('property_id', $propertyId)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->value('id');

        if ($latestScanId === null) {
            return null;
        }

        $value = ScanMetric::withoutGlobalScopes()
            ->where('scan_id', $latestScanId)
            ->where('metric_name', 'accessibility_risk_score')
            ->whereNull('page_id')
            ->value('metric_value');

        return $value !== null ? round((float) $value, 1) : null;
    }
}
