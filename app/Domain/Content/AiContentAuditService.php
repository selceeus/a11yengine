<?php

namespace App\Domain\Content;

use App\Ai\Agents\ContentAuditAgent;
use App\Enums\ContentAuditStatus;
use App\Models\ContentAudit;
use App\Models\Finding;
use App\Models\Issue;
use App\Models\Scan;
use App\Models\ScanPage;
use App\Services\RagRetrievalService;
use Illuminate\Support\Facades\Date;

class AiContentAuditService
{
    /**
     * Axe rule keys considered content-level issues.
     */
    private const CONTENT_RULES = [
        // Axe-core rules
        'link-name',
        'link-in-text-block',
        'heading-order',
        'empty-heading',
        'page-has-heading-one',
        'label',
        'form-field-multiple-labels',
        'select-name',
        'input-button-name',
        'image-alt',
        'image-redundant-alt',
        'input-image-alt',
        'svg-img-alt',
        'document-title',
        // Deterministic content runner rules
        'content-img-filename-alt',
        'content-img-generic-alt',
        'content-img-long-alt',
        'content-img-redundant-descriptor',
        'content-link-url-as-text',
        'content-duplicate-link-text',
        'content-multiple-h1',
        'content-generic-page-title',
        'content-video-missing-captions',
        'content-video-missing-transcript',
        'content-audio-missing-transcript',
        'content-youtube-captions-unknown',
        'content-vimeo-captions-unknown',
        'content-video-embed-unverified',
    ];

    public function __construct(
        private readonly RagRetrievalService $ragService,
        private readonly ContentMetricsService $metricsService,
    ) {}

    /**
     * Generate AI-powered content issue observations for the given ContentAudit record.
     */
    public function generate(ContentAudit $audit): void
    {
        $propertyName = $audit->property?->name ?? 'Unknown';

        // Resolve the latest completed scan for this property
        $latestScanId = Scan::withoutGlobalScopes()
            ->where('property_id', $audit->property_id)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->value('id');

        // Load content-relevant findings from the latest scan (or all property findings if no scan)
        $findingsQuery = Finding::withoutGlobalScopes()
            ->whereIn('rule_key', self::CONTENT_RULES);

        if ($latestScanId !== null) {
            $findingsQuery->where('scan_id', $latestScanId);
        } else {
            $findingsQuery->where('property_id', $audit->property_id);
        }

        $findings = $findingsQuery
            ->get(['page_url', 'rule_key', 'element_html', 'element_identifier', 'message', 'severity', 'wcag_category', 'wcag_criteria', 'description', 'tags'])
            ->groupBy('page_url');

        // Take the top 20 pages by finding count
        $topPages = $findings
            ->sortByDesc(fn ($group) => $group->count())
            ->take(20);

        // Load matching Issue records for linkage (rule_key + page_url + property_id)
        $issueIndex = Issue::withoutGlobalScopes()
            ->where('property_id', $audit->property_id)
            ->whereIn('rule_key', self::CONTENT_RULES)
            ->get(['id', 'rule_key', 'page_url'])
            ->keyBy(fn (Issue $issue) => $issue->rule_key.'|'.$issue->page_url);

        // Build page context array: findings only (no HTML fetching)
        $pageContexts = $topPages->map(function ($pageFindings, string $url) use ($issueIndex): array {
            return [
                'url' => $url,
                'findings' => $pageFindings->map(fn (Finding $f) => [
                    'rule_key' => $f->rule_key,
                    'element_html' => $f->element_html,
                    'element_identifier' => $f->element_identifier,
                    'message' => $f->message,
                    'severity' => $f->severity instanceof \App\Enums\FindingSeverity
                        ? $f->severity->value
                        : (string) $f->severity,
                    'wcag_criteria' => $f->wcag_criteria,
                    'issue_id' => $issueIndex->get($f->rule_key.'|'.$url)?->id,
                ])->values()->all(),
            ];
        })->values()->all();

        $prompt = $this->buildPrompt($pageContexts, $propertyName);

        $response = ContentAuditAgent::make()->prompt($prompt);
        $result = json_decode($response->text, true) ?? [];
        $contentIssues = $result['content_issues'] ?? [];

        // Enrich each issue with the matched issue_id if not already set by AI
        $contentIssues = array_map(function (array $issue) use ($issueIndex): array {
            if (empty($issue['issue_id'])) {
                $key = ($issue['rule_key'] ?? '').'|'.($issue['page_url'] ?? '');
                $issue['issue_id'] = $issueIndex->get($key)?->id;
            }

            return $issue;
        }, $contentIssues);

        $pagesAnalyzed = count($topPages);

        // Compute reading metrics server-side using Flesch-Kincaid (no LLM estimation)
        $pageUrls = array_column($pageContexts, 'url');
        $visibleTextByUrl = ScanPage::withoutGlobalScopes()
            ->whereIn('url', $pageUrls)
            ->whereNotNull('visible_text')
            ->pluck('visible_text', 'url');

        $readingMetrics = collect($pageUrls)
            ->map(fn (string $url) => $this->metricsService->computeMetrics(
                $visibleTextByUrl->get($url, ''),
                $url,
            ))
            ->filter(fn (array $m) => $m['word_count'] > 0)
            ->values()
            ->all();

        // Compute site-wide averages from per-page metrics
        $avgReadingLevel = null;
        $avgReadingTimeSeconds = null;

        if (! empty($readingMetrics)) {
            $grades = collect($readingMetrics)
                ->map(fn (array $m) => $this->extractGradeNumber($m['reading_level'] ?? ''))
                ->filter()
                ->values();

            if ($grades->isNotEmpty()) {
                $avgGrade = (int) round($grades->average());
                $avgReadingLevel = "Grade {$avgGrade} (Flesch-Kincaid)";
            }

            $times = collect($readingMetrics)
                ->pluck('reading_time_seconds')
                ->filter()
                ->values();

            if ($times->isNotEmpty()) {
                $avgReadingTimeSeconds = (int) round($times->average());
            }
        }

        $audit->update([
            'content_issues' => $contentIssues,
            'total_issues' => count($contentIssues),
            'pages_analyzed' => $pagesAnalyzed,
            'reading_metrics' => $readingMetrics,
            'avg_reading_level' => $avgReadingLevel,
            'avg_reading_time_seconds' => $avgReadingTimeSeconds,
            'prompt_context' => $prompt,
            'raw_ai_response' => $response->text,
            'status' => ContentAuditStatus::Completed,
            'generated_at' => Date::now(),
        ]);
    }

    /**
     * Extract the numeric Flesch-Kincaid grade from a reading level string.
     */
    private function extractGradeNumber(string $level): ?int
    {
        if (preg_match('/grade\s+(\d+)/i', $level, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Build the structured content audit prompt.
     *
     * @param  array<int, array<string, mixed>>  $pages
     */
    public function buildPrompt(array $pages, string $propertyName): string
    {
        $pagesJson = json_encode($pages, JSON_PRETTY_PRINT);
        $count = count($pages);

        $ragSection = $this->buildRagSection();

        return <<<PROMPT
You are auditing the content accessibility of the website "{$propertyName}".

## Task
Analyse {$count} page(s) for content-level accessibility issues that automated scanners may have flagged or missed. Focus on:
- Ambiguous or vague link text (e.g. "click here", "read more")
- Poorly structured or missing headings
- Missing or misleading alt text on images
- Unclear or absent form labels
- Document title issues
- Video and media accessibility (captions, transcripts)
- Any other content clarity or readability problems that affect assistive technology users

## Pages & Findings
Each page entry includes:
- `url` — the page URL
- `findings` — automated scanner findings filtered to content-related rules, each with `rule_key`, `element_html`, `message`, `severity`, `wcag_criteria`, and `issue_id` (null if no matching issue record exists)

{$pagesJson}

{$ragSection}
---

For each distinct content problem you identify, return a JSON object under `content_issues`. Use the scanner findings as a starting point but also identify problems the automated scan may have missed from the HTML snippet.

Return a single JSON object matching this exact schema (no prose, no markdown fences):

{
  "content_issues": [
    {
      "page_url": "<URL of the page>",
      "issue_id": <integer matching finding's issue_id, or null if not matched>,
      "rule_key": "<axe or content rule key or 'manual' for issues found only in the findings context>",
      "category": "link_text|alt_text|heading_structure|form_label|readability|video_media",
      "issue_type": "<short human-readable type label>",
      "element_html": "<the offending HTML element>",
      "current_text": "<the current visible or attribute text, or null>",
      "issue": "<1-2 sentence description of the problem>",
      "suggestion": "<specific, actionable recommendation>",
      "suggested_alt_text": "<for alt_text issues only: the exact string to use as the alt attribute value; use empty string \"\" for decorative images; null for all other categories>",
      "severity": "critical|serious|moderate|minor",
      "wcag_criteria": "<WCAG criterion, e.g. 2.4.4>",
      "writer_note": "<guidance for content editors to fix this>",
      "developer_note": "<guidance for developers to implement the fix>"
    }
  ]
}

Rules:
- Report at most 30 issues total across all pages.
- Prioritise critical and serious issues first.
- Do not invent issues not evidenced by the findings data.
- For `alt_text` issues: write a concise, descriptive `suggested_alt_text` value — the exact text to place in the `alt` attribute, written in plain language from the user's perspective. Use `""` for purely decorative images. Set `suggested_alt_text` to `null` for all other categories.
PROMPT;
    }

    /**
     * Build a supplementary WCAG context block for content accessibility rules.
     * Returns an empty string if the knowledge base is empty or unavailable.
     */
    private function buildRagSection(): string
    {
        try {
            $wcagChunks = $this->ragService->findWcagChunks(
                'image alt text link heading label form document title',
                5,
                ['1.1.1', '1.3.1', '2.4.4', '2.4.2', '4.1.2'],
            );

            if (empty($wcagChunks)) {
                return '';
            }

            $sections = "## WCAG Content Accessibility Guidance (Knowledge Base)\n";

            foreach ($wcagChunks as $chunk) {
                $sections .= "\n**{$chunk['criterion']} {$chunk['title']}**: {$chunk['chunk']}";
            }

            return $sections."\n\n";
        } catch (\Throwable) {
            return '';
        }
    }
}
