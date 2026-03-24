<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Timeout(300)]
class ContentAuditAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are an expert web accessibility content auditor. Identify content-level accessibility issues from page HTML and automated findings.';
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'content_issues' => $schema->array()->items(
                $schema->object([
                    'page_url' => $schema->string()->required(),
                    'issue_id' => $schema->integer()->nullable()->required(),
                    'rule_key' => $schema->string()->required(),
                    'category' => $schema->string()->enum(['link_text', 'alt_text', 'heading_structure', 'form_label', 'readability'])->required(),
                    'issue_type' => $schema->string()->required(),
                    'element_html' => $schema->string()->nullable()->required(),
                    'current_text' => $schema->string()->nullable()->required(),
                    'issue' => $schema->string()->required(),
                    'suggestion' => $schema->string()->required(),
                    'severity' => $schema->string()->enum(['critical', 'serious', 'moderate', 'minor'])->required(),
                    'wcag_criteria' => $schema->string()->required(),
                    'writer_note' => $schema->string()->required(),
                    'developer_note' => $schema->string()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
            'reading_metrics' => $schema->array()->items(
                $schema->object([
                    'page_url' => $schema->string()->required(),
                    'reading_level' => $schema->string()->required(),
                    'reading_time' => $schema->string()->required(),
                    'reading_time_seconds' => $schema->integer()->required(),
                    'word_count' => $schema->integer()->required(),
                    'flesch_score' => $schema->number()->nullable()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
        ];
    }
}
