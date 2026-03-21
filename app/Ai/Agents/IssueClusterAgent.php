<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class IssueClusterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are an expert web accessibility engineer specializing in identifying root causes and grouping issues into actionable clusters.';
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'clusters' => $schema->array()->items(
                $schema->object([
                    'cluster_name' => $schema->string()->required(),
                    'common_component' => $schema->string()->nullable(),
                    'recommended_fix' => $schema->string()->required(),
                    'severity' => $schema->string()->enum(['critical', 'high', 'medium', 'low'])->required(),
                    'priority' => $schema->string()->enum(['high', 'medium', 'low'])->required(),
                    'issue_ids' => $schema->array()->items($schema->integer())->required(),
                    'wcag_categories' => $schema->array()->items($schema->string())->required(),
                    'affected_pages' => $schema->integer()->required(),
                    'ai_notes' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
