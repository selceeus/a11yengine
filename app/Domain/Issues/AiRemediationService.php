<?php

namespace App\Domain\Issues;

use App\Ai\Agents\RemediationAgent;
use App\Models\Issue;
use App\Services\RagRetrievalService;
use Illuminate\Support\Facades\Cache;

class AiRemediationService
{
    public function __construct(private readonly RagRetrievalService $ragService) {}

    /**
     * Hours to cache results per rule + WCAG criterion + description + model.
     * Shared across all issues with matching rule/criterion so the same fix
     * is not re-generated for every property it appears on.
     */
    private const CACHE_TTL_HOURS = 24;

    /**
     * Generate (or return cached) AI remediation suggestions for the given issue.
     *
     * @return array{
     *     explanation: string,
     *     wcag_reference: string,
     *     wcag_level: string,
     *     user_impact: string,
     *     severity_rating: string,
     *     code_fix: string|null,
     *     aria_fix: string|null,
     *     remediation_steps: list<string>,
     *     testing_guidance: string,
     *     estimated_effort: string,
     *     resources: list<array{title: string, url: string}>,
     *     legal_precedents: list<array{case_name: string, year: int|null, outcome: string, industry_relevance: string, summary: string}>,
     *     legal_risk_rating: string,
     *     wcag_grounding: string,
     *     similar_resolutions: list<array{rule_key: string, approach: string, resolved_count: int}>,
     * }
     */
    public function generate(Issue $issue): array
    {
        return Cache::remember(
            $this->cacheKey($issue),
            now()->addHours(self::CACHE_TTL_HOURS),
            function () use ($issue): array {
                $response = RemediationAgent::make()->prompt($this->buildPrompt($issue));

                return json_decode($response->text, true) ?? [];
            }
        );
    }

    /**
     * Cache key scoped to model + rule + WCAG criterion + description.
     * Including the model name ensures a cache miss when the AI model is upgraded.
     * Including description ensures a cache miss when rule metadata meaningfully changes.
     */
    public function cacheKey(Issue $issue): string
    {
        $model = config('ai.default', 'openai');

        return 'ai_remediation:'.$model.':'.md5(
            $issue->rule_key.($issue->wcag_criteria ?? '').($issue->description ?? '')
        );
    }

    private function buildPrompt(Issue $issue): string
    {
        $ruleKey = $issue->rule_key;
        $description = $issue->description ?? 'No description available.';
        $severity = $issue->severity->value;
        $wcagCriteria = $issue->wcag_criteria ?? 'unknown';
        $wcagCategory = $issue->wcag_category ?? 'unknown';
        $occurrences = $issue->occurrence_count;
        $tags = $issue->tags ? implode(', ', $issue->tags) : 'none';
        $helpUrl = $issue->help_url ?? 'N/A';

        $sampleHtml = $issue->findings()
            ->whereNotNull('element_html')
            ->value('element_html');

        $pages = $issue->findings()
            ->whereNotNull('page_url')
            ->distinct('page_url')
            ->limit(5)
            ->pluck('page_url')
            ->toArray();

        $prompt = <<<PROMPT
You are reviewing a specific accessibility issue detected by an automated scan.

## Issue Details

- **Rule**: {$ruleKey}
- **Description**: {$description}
- **Severity**: {$severity}
- **WCAG Criterion**: {$wcagCriteria}
- **WCAG Category**: {$wcagCategory}
- **Occurrences detected**: {$occurrences}
- **Tags**: {$tags}
- **Reference**: {$helpUrl}
PROMPT;

        if ($sampleHtml) {
            $prompt .= "\n\n## Sample Affected HTML Element\n\n```html\n{$sampleHtml}\n```";
        }

        if ($pages) {
            $pagesText = implode("\n  - ", $pages);
            $prompt .= "\n\n## Affected Pages (sample)\n\n  - {$pagesText}";
        }

        $criterionNumber = (string) preg_replace('/\s+[A-Z]+$/', '', $wcagCriteria);
        $industryValue = $issue->property?->industry?->value;
        $industries = $industryValue ? [$industryValue] : null;
        $ragContext = $this->buildRagContext($description, $criterionNumber, $industries);

        if ($ragContext !== '') {
            $prompt .= $ragContext;
        }

        $schema = <<<'SCHEMA'
{
  "explanation": "<2-3 sentences explaining why this issue harms accessibility>",
  "wcag_reference": "<Full criterion name and number, e.g. '1.1.1 Non-text Content (Level A)'>",
  "wcag_level": "<A|AA|AAA>",
  "user_impact": "<1-2 sentences on who is affected and how>",
  "severity_rating": "<critical|serious|moderate|minor>",
  "code_fix": "<HTML/ARIA snippet that resolves the issue, or null if not applicable>",
  "aria_fix": "<ARIA-specific snippet if the fix involves ARIA attributes, or null>",
  "remediation_steps": ["<step 1>", "<step 2>", "<step 3>"],
  "testing_guidance": "<How to verify the fix with assistive technology or automated tools>",
  "estimated_effort": "<low|medium|high>",
  "resources": [
    {"title": "<resource title>", "url": "<URL>"}
  ],
  "legal_precedents": [
    {
      "case_name": "<ADA case name>",
      "year": <year filed or null>,
      "outcome": "<plaintiff_won|defendant_won|settled>",
      "industry_relevance": "<why this case is relevant to this violation type or industry>",
      "summary": "<brief summary of the ruling and its accessibility implications>"
    }
  ],
  "legal_risk_rating": "<high|medium|low>",
  "wcag_grounding": "<quote or close paraphrase of the specific WCAG criterion text that underpins this issue>",
  "similar_resolutions": [
    {
      "rule_key": "<rule key from a past resolved issue>",
      "approach": "<summary of the remediation approach that worked>",
      "resolved_count": <integer count of issues resolved with this approach>
    }
  ]
}
SCHEMA;

        $prompt .= "\n\n---\n\nGenerate a detailed remediation guide for this specific issue. ";
        $prompt .= "Use the ADA lawsuit precedents from the context above to populate \`legal_precedents\` ";
        $prompt .= "and set \`legal_risk_rating\` based on how often similar violations have been successfully litigated. ";
        $prompt .= "Set \`wcag_grounding\` by quoting or closely paraphrasing the WCAG criterion text from the knowledge base. ";
        $prompt .= "Populate \`similar_resolutions\` from the past remediations provided. ";
        $prompt .= "Respond with a single JSON object matching this exact schema (no prose, no markdown fences):\n\n{$schema}";

        return $prompt;
    }

    /**
     * Build a supplementary context block from the RAG knowledge base.
     * Returns an empty string if the knowledge base is empty or unavailable.
     */
    private function buildRagContext(string $description, string $criterion, ?array $industries = null): string
    {
        try {
            $sections = '';

            $wcagChunks = $this->ragService->findWcagChunks($description, 3, [$criterion]);

            if (! empty($wcagChunks)) {
                $sections .= "\n\n## WCAG Guidance (Knowledge Base)\n";

                foreach ($wcagChunks as $chunk) {
                    $sections .= "\n**{$chunk['criterion']} {$chunk['title']}**: {$chunk['chunk']}";
                }
            }

            $lawsuits = $this->ragService->findLawsuits($description, 3, $industries);

            if (! empty($lawsuits)) {
                $sections .= "\n\n## Relevant ADA Lawsuit Precedents\n";

                foreach ($lawsuits as $lawsuit) {
                    $year = $lawsuit['filed_year'] ?? 'unknown year';
                    $sections .= "\n- **{$lawsuit['case_name']}** ({$year}, {$lawsuit['outcome']}): {$lawsuit['summary']}";
                }
            }

            $remediations = $this->ragService->findSimilarRemediations($description, 3);

            if (! empty($remediations)) {
                $sections .= "\n\n## Similar Past Remediations\n";

                foreach ($remediations as $rem) {
                    $wcag = $rem['wcag_criteria'] ? " ({$rem['wcag_criteria']})" : '';
                    $count = $rem['resolved_count'] ?? 1;
                    $sections .= "\n- `{$rem['rule_key']}`{$wcag} [{$count} resolved]: {$rem['resolution']}";
                }
            }

            return $sections;
        } catch (\Throwable) {
            return '';
        }
    }
}
