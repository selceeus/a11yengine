<?php

namespace App\Domain\Issues;

use App\Models\Issue;
use App\Services\AiClient;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class AiRemediationService
{
    /**
     * Hours to cache results per rule + WCAG criterion + description + model.
     * Shared across all issues with matching rule/criterion so the same fix
     * is not re-generated for every property it appears on.
     */
    private const CACHE_TTL_HOURS = 24;

    private string $driver;

    private string $model;

    public function __construct(private readonly AiClient $client)
    {
        $this->driver = config('ai.driver', 'openai');
        $this->model = config("ai.providers.{$this->driver}.model", 'default');
    }

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
     * }
     */
    public function generate(Issue $issue): array
    {
        $apiKey = config("ai.providers.{$this->driver}.api_key");

        if (empty($apiKey)) {
            throw new RuntimeException("AI provider [{$this->driver}] api_key is not configured.");
        }

        return Cache::remember(
            $this->cacheKey($issue),
            now()->addHours(self::CACHE_TTL_HOURS),
            function () use ($issue): array {
                $prompt = $this->buildPrompt($issue);

                $raw = $this->getContent($this->client->chat([
                    [
                        'role' => 'system',
                        'content' => 'You are an Accessibility Developer Assistant. Return valid JSON only — no markdown, no prose outside the JSON object.',
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ]));

                return $this->client->decodeJson($raw);
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
        return 'ai_remediation:'.$this->model.':'.md5(
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
  ]
}
SCHEMA;

        $prompt .= "\n\n---\n\nGenerate a detailed remediation guide for this specific issue. ";
        $prompt .= "Respond with a single JSON object matching this exact schema (no prose, no markdown fences):\n\n{$schema}";

        return $prompt;
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
