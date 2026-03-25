<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Timeout(180)]
class RemediationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are an Accessibility Developer Assistant specializing in WCAG compliance and remediation guidance.';
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'explanation' => $schema->string()->required(),
            'wcag_reference' => $schema->string()->required(),
            'wcag_level' => $schema->string()->enum(['A', 'AA', 'AAA'])->required(),
            'user_impact' => $schema->string()->required(),
            'severity_rating' => $schema->string()->enum(['critical', 'serious', 'moderate', 'minor'])->required(),
            'code_fix' => $schema->string()->nullable()->required(),
            'aria_fix' => $schema->string()->nullable()->required(),
            'remediation_steps' => $schema->array()->items($schema->string())->required(),
            'testing_guidance' => $schema->string()->required(),
            'estimated_effort' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
            'resources' => $schema->array()->items(
                $schema->object([
                    'title' => $schema->string()->required(),
                    'url' => $schema->string()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
            'legal_precedents' => $schema->array()->items(
                $schema->object([
                    'case_name' => $schema->string()->required(),
                    'year' => $schema->integer()->nullable()->required(),
                    'outcome' => $schema->string()->required(),
                    'industry_relevance' => $schema->string()->required(),
                    'summary' => $schema->string()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
            'legal_risk_rating' => $schema->string()->enum(['high', 'medium', 'low'])->required(),
            'wcag_grounding' => $schema->string()->required(),
            'similar_resolutions' => $schema->array()->items(
                $schema->object([
                    'rule_key' => $schema->string()->required(),
                    'approach' => $schema->string()->required(),
                    'resolved_count' => $schema->integer()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
        ];
    }
}
