<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class RiskAdvisoryAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are an expert web accessibility engineer specializing in risk prioritisation and remediation planning.';
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'priorities' => $schema->array()->items(
                $schema->object([
                    'rank' => $schema->integer()->required(),
                    'issue_id' => $schema->integer()->required(),
                    'title' => $schema->string()->required(),
                    'rule_key' => $schema->string()->required(),
                    'severity' => $schema->string()->enum(['critical', 'serious', 'moderate', 'minor'])->required(),
                    'risk_reduction_score' => $schema->integer()->required(),
                    'ease_of_remediation' => $schema->string()->enum(['easy', 'moderate', 'complex'])->required(),
                    'user_impact' => $schema->string()->enum(['high', 'medium', 'low'])->required(),
                    'compliance_importance' => $schema->string()->enum(['high', 'medium', 'low'])->required(),
                    'affected_pages' => $schema->integer()->required(),
                    'affected_page_urls' => $schema->array()->items($schema->string())->required(),
                    'quick_win' => $schema->boolean()->required(),
                    'rationale' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
