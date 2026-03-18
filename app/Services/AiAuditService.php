<?php

namespace App\Services;

use App\Domain\Audits\GatherAuditContext;
use App\Enums\AuditStatus;
use App\Models\Audit;
use Illuminate\Support\Facades\Date;
use RuntimeException;

class AiAuditService
{
    public function __construct(
        private readonly GatherAuditContext $contextGatherer,
        private readonly AiClient $client,
    ) {}

    /**
     * Generate the AI audit for the given Audit record, populating all result fields.
     */
    public function generate(Audit $audit): void
    {
        $driver = config('ai.driver', 'openai');
        $apiKey = config("ai.providers.{$driver}.api_key");

        if (empty($apiKey)) {
            throw new RuntimeException("AI provider [{$driver}] api_key is not configured.");
        }

        $context = $this->contextGatherer->handle(
            $audit->property_id,
            $audit->source_scan_ids ?? []
        );

        $prompt = $this->buildPrompt($context);

        $raw = $this->getContent($this->client->chat([
            ['role' => 'system', 'content' => 'You are an expert web accessibility auditor. Return valid JSON only — no markdown, no prose outside the JSON object.'],
            ['role' => 'user', 'content' => $prompt],
        ]));

        $result = $this->client->decodeJson($raw);

        $audit->update([
            'prompt_context' => $prompt,
            'raw_ai_response' => $raw,
            'executive_summary' => $result['executive_summary'] ?? null,
            'compliance_status' => $result['compliance_status'] ?? null,
            'top_risks' => $result['top_risks'] ?? null,
            'issue_details' => $result['issue_details'] ?? null,
            'remediations' => $result['remediations'] ?? null,
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
  ]
}
PROMPT;
    }

    /**
     * Extract the text content from the AI provider response.
     *
     * @param  array<string, mixed>  $response
     */
    private function getContent(array $response): string
    {
        // OpenAI: choices[0].message.content
        if (isset($response['choices'][0]['message']['content'])) {
            return (string) $response['choices'][0]['message']['content'];
        }

        // Anthropic: content[0].text
        if (isset($response['content'][0]['text'])) {
            return (string) $response['content'][0]['text'];
        }

        return '';
    }
}
