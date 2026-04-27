<?php

namespace App\Jobs;

use App\Models\Issue;
use App\Models\RemediationEmbedding;
use App\Services\EmbeddingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class IndexRemediationPatternJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(public readonly Issue $issue) {}

    public function handle(EmbeddingService $embeddingService): void
    {
        $suggestions = $this->issue->ai_suggestions;

        if (empty($suggestions)) {
            return;
        }

        $explanation = $suggestions['explanation'] ?? '';
        $remediationSteps = $suggestions['remediation_steps'] ?? [];
        $codeFix = $suggestions['code_fix'] ?? '';
        $ariaFix = $suggestions['aria_fix'] ?? '';

        $resolution = implode(' ', array_filter([
            $codeFix,
            $ariaFix,
            is_array($remediationSteps) ? implode(' ', $remediationSteps) : '',
        ]));

        if (empty($explanation) && empty($resolution)) {
            return;
        }

        $textToEmbed = sprintf(
            '%s %s %s %s',
            $this->issue->rule_key,
            $this->issue->wcag_criteria ?? '',
            $explanation,
            $resolution
        );

        $embedding = $embeddingService->embed($textToEmbed);

        RemediationEmbedding::updateOrCreate(
            ['issue_id' => $this->issue->id],
            [
                'rule_key' => $this->issue->rule_key,
                'wcag_criteria' => $this->issue->wcag_criteria,
                'description' => $explanation,
                'resolution' => $resolution,
                'outcome' => $suggestions['severity_rating'] ?? null,
                'embedding' => $embedding,
                'metadata' => [
                    'wcag_category' => $this->issue->wcag_category,
                    'wcag_level' => $suggestions['wcag_level'] ?? null,
                    'estimated_effort' => $suggestions['estimated_effort'] ?? null,
                    'indexed_at' => now()->toIso8601String(),
                ],
            ]
        );
    }

    public function failed(Throwable $e): void
    {
        logger()->error('IndexRemediationPatternJob failed', [
            'issue_id' => $this->issue->id,
            'rule_key' => $this->issue->rule_key,
            'error' => $e->getMessage(),
        ]);
    }
}
