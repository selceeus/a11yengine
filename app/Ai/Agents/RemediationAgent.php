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
        ];
    }
}
