<?php

namespace App\Services;

use App\Ai\Agents\AuditAgent;
use App\Domain\Audits\GatherAuditContext;
use App\Enums\AuditStatus;
use App\Models\Audit;
use Illuminate\Support\Facades\Date;

class AiAuditService
{
    public function __construct(
        private readonly GatherAuditContext $contextGatherer,
        private readonly RagRetrievalService $ragService,
    ) {}

    /**
     * Generate the AI audit for the given Audit record, populating all result fields.
     */
    public function generate(Audit $audit): void
    {
        $context = $this->contextGatherer->handle(
            $audit->property_id,
            $audit->source_scan_ids ?? []
        );

        $prompt = $this->buildPrompt($context);

        $response = AuditAgent::make()->prompt($prompt);
        $result = json_decode($response->text, true) ?? [];

        $audit->update([
            'prompt_context' => $prompt,
            'raw_ai_response' => $response->text,
            'executive_summary' => $result['executive_summary'] ?? null,
            'compliance_status' => $result['compliance_status'] ?? null,
            'top_risks' => $result['top_risks'] ?? null,
            'issue_details' => $result['issue_details'] ?? null,
            'remediations' => $result['remediations'] ?? null,
            'legal_precedents' => $result['legal_precedents'] ?? [],
            'summary_statistics' => $result['summary_statistics'] ?? null,
            'overall_score' => isset($result['overall_score']) ? (int) $result['overall_score'] : null,
            'status' => AuditStatus::Completed,
            'generated_at' => Date::now(),
        ]);
    }

    /**
     * Build the structured prompt for the AI model.
     *
     * @param  array<string, mixed>  $context
     */
    public function buildPrompt(array $context): string
    {
        $propertyName = $context['property']['name'] ?? 'Unknown';
        $baseUrl = $context['property']['base_url'] ?? '';
        $scansJson = json_encode($context['scans'] ?? [], JSON_PRETTY_PRINT);
        $issuesJson = json_encode($context['issues'] ?? [], JSON_PRETTY_PRINT);
        $severityJson = json_encode($context['severity_breakdown'] ?? [], JSON_PRETTY_PRINT);
        $pagesJson = json_encode($context['top_pages'] ?? [], JSON_PRETTY_PRINT);
        $lighthouse = $context['lighthouse'] ?? [];

        $lhPerf = $lighthouse['performance'] ?? 'N/A';
        $lhA11y = $lighthouse['accessibility'] ?? 'N/A';
        $lhBp = $lighthouse['best_practices'] ?? 'N/A';
        $lhSeo = $lighthouse['seo'] ?? 'N/A';

        $ragSection = $this->buildRagSection($context);

        return <<<PROMPT
You are auditing the accessibility of the website "{$propertyName}" ({$baseUrl}).

## Recent Scans
{$scansJson}

## Lighthouse Averages
Performance: {$lhPerf} | Accessibility: {$lhA11y} | Best Practices: {$lhBp} | SEO: {$lhSeo}

## Finding Severity Breakdown
{$severityJson}

## Top Pages by Violations
{$pagesJson}

## Top Open Issues (by risk weight, highest first)
{$issuesJson}

{$ragSection}
---

Respond with a single JSON object matching this exact schema (no prose, no markdown fences):

{
  "overall_score": <integer 0-100, higher is better>,
  "executive_summary": "<2-4 paragraph narrative summary of the accessibility state>",
  "compliance_status": {
    "wcag_a":   { "status": "pass|partial|fail", "notes": "<brief note>" },
    "wcag_aa":  { "status": "pass|partial|fail", "notes": "<brief note>" },
    "wcag_aaa": { "status": "pass|partial|fail", "notes": "<brief note>" }
  },
  "summary_statistics": {
    "total_issues": <int>,
    "critical": <int>,
    "serious": <int>,
    "moderate": <int>,
    "minor": <int>
  },
  "top_risks": [
    {
      "rank": <int>,
      "title": "<concise risk title>",
      "severity": "critical|serious|moderate|minor",
      "wcag_criteria": "<e.g. 1.1.1>",
      "impact": "<user impact description>",
      "occurrences": <int>
    }
  ],
  "issue_details": [
    {
      "rule_key": "<axe rule id>",
      "title": "<human readable title>",
      "severity": "critical|serious|moderate|minor",
      "wcag_criteria": "<criterion>",
      "description": "<what the issue is>",
      "affected_pages": <int>,
      "remediation_hint": "<one-sentence fix>"
    }
  ],
  "remediations": [
    {
      "priority": "high|medium|low",
      "title": "<remediation title>",
      "description": "<what to fix and why>",
      "steps": ["<step 1>", "<step 2>"],
      "code_example": "<optional short code snippet or empty string>"
    }
  ],
  "legal_precedents": [
    {
      "case_name": "<ADA case name from knowledge base>",
      "year": <year filed or null>,
      "outcome": "plaintiff_won|defendant_won|settled",
      "relevance": "<1-2 sentences explaining why this case is relevant to the issues found in this audit>"
    }
  ]
}

Rules:
- Base all numbers on the provided data — do not fabricate statistics.
- For `legal_precedents`, only reference cases from the ADA Legal Precedents section above — do not invent case names. If no precedents are available, return an empty array.
PROMPT;
    }

    private function buildRagSection(array $context): string
    {
        try {
            $sections = '';
            $industry = $context['property']['industry'] ?? null;

            $lawsuits = $this->ragService->findLawsuits(
                'web accessibility ADA compliance violations legal risk '.($industry ?? ''),
                3,
                $industry !== null ? [$industry] : null,
            );

            if (! empty($lawsuits)) {
                $sections .= "## ADA Legal Precedents (Knowledge Base)\n";

                foreach ($lawsuits as $case) {
                    $settlement = $case['settlement_amount'] !== null
                        ? ' — $'.number_format((int) $case['settlement_amount']).' settlement'
                        : '';
                    $sections .= "\n- **{$case['case_name']}** ({$case['filed_year']}, {$case['industry']}): {$case['summary']}{$settlement}";
                }

                $sections .= "\n\n";
            }

            return $sections;
        } catch (\Throwable) {
            return '';
        }
    }
}
