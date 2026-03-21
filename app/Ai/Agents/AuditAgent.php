<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class AuditAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are an expert web accessibility auditor. Analyse the provided data and produce a structured accessibility audit report.';
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'overall_score' => $schema->integer()->required(),
            'executive_summary' => $schema->string()->required(),
            'compliance_status' => $schema->object([
                'wcag_a' => $schema->object([
                    'status' => $schema->string()->enum(['pass', 'partial', 'fail'])->required(),
                    'notes' => $schema->string()->required(),
                ])->required(),
                'wcag_aa' => $schema->object([
                    'status' => $schema->string()->enum(['pass', 'partial', 'fail'])->required(),
                    'notes' => $schema->string()->required(),
                ])->required(),
                'wcag_aaa' => $schema->object([
                    'status' => $schema->string()->enum(['pass', 'partial', 'fail'])->required(),
                    'notes' => $schema->string()->required(),
                ])->required(),
            ])->required(),
            'summary_statistics' => $schema->object([
                'total_issues' => $schema->integer()->required(),
                'critical' => $schema->integer()->required(),
                'serious' => $schema->integer()->required(),
                'moderate' => $schema->integer()->required(),
                'minor' => $schema->integer()->required(),
            ])->required(),
            'top_risks' => $schema->array()->items(
                $schema->object([
                    'rank' => $schema->integer()->required(),
                    'title' => $schema->string()->required(),
                    'severity' => $schema->string()->enum(['critical', 'serious', 'moderate', 'minor'])->required(),
                    'wcag_criteria' => $schema->string()->required(),
                    'impact' => $schema->string()->required(),
                    'occurrences' => $schema->integer()->required(),
                ])
            )->required(),
            'issue_details' => $schema->array()->items(
                $schema->object([
                    'rule_key' => $schema->string()->required(),
                    'title' => $schema->string()->required(),
                    'severity' => $schema->string()->enum(['critical', 'serious', 'moderate', 'minor'])->required(),
                    'wcag_criteria' => $schema->string()->required(),
                    'description' => $schema->string()->required(),
                    'affected_pages' => $schema->integer()->required(),
                    'remediation_hint' => $schema->string()->required(),
                ])
            )->required(),
            'remediations' => $schema->array()->items(
                $schema->object([
                    'priority' => $schema->string()->enum(['high', 'medium', 'low'])->required(),
                    'title' => $schema->string()->required(),
                    'description' => $schema->string()->required(),
                    'steps' => $schema->array()->items($schema->string())->required(),
                    'code_example' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
