<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class GovernanceAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are an expert web accessibility governance consultant. Produce structured governance reports from accessibility data.';
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'executive_narrative' => $schema->string()->required(),
            'summary_cards' => $schema->array()->items(
                $schema->object([
                    'title' => $schema->string()->required(),
                    'value' => $schema->number()->required(),
                    'delta' => $schema->number()->required(),
                    'trend' => $schema->string()->enum(['up', 'down', 'stable'])->required(),
                    'unit' => $schema->string()->nullable(),
                ])
            )->required(),
            'recommendations' => $schema->array()->items(
                $schema->object([
                    'priority' => $schema->string()->enum(['high', 'medium', 'low'])->required(),
                    'title' => $schema->string()->required(),
                    'rationale' => $schema->string()->required(),
                    'category' => $schema->string()->required(),
                    'action' => $schema->string()->required(),
                    'due_by_quarter' => $schema->string()->required(),
                    'source_refs' => $schema->array()->items(
                        $schema->object([
                            'type' => $schema->string()->enum(['issue', 'scan', 'audit', 'advisory', 'content_audit'])->required(),
                            'id' => $schema->integer()->required(),
                            'label' => $schema->string()->required(),
                            'url' => $schema->string()->required(),
                        ])
                    )->required(),
                ])
            )->required(),
        ];
    }
}
