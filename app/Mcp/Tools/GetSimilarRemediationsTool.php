<?php

namespace App\Mcp\Tools;

use App\Services\RagRetrievalService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Return past successful remediation patterns for a given accessibility rule and WCAG criterion, ranked by semantic similarity and enriched with resolved issue counts.')]
class GetSimilarRemediationsTool extends Tool
{
    public function __construct(private readonly RagRetrievalService $ragService) {}

    public function handle(Request $request): Response
    {
        $request->validate([
            'rule_key' => ['required', 'string'],
            'wcag_criteria' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $query = $request->get('rule_key').' '.$request->get('wcag_criteria');
        $limit = (int) ($request->get('limit') ?? 5);

        $remediations = $this->ragService->findSimilarRemediations($query, $limit);

        return Response::json([
            'rule_key' => $request->get('rule_key'),
            'wcag_criteria' => $request->get('wcag_criteria'),
            'total' => count($remediations),
            'remediations' => $remediations,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'rule_key' => $schema->string()->description('The axe-core rule key (e.g. image-alt, color-contrast).')->required(),
            'wcag_criteria' => $schema->string()->description('The WCAG criterion number (e.g. 1.1.1, 1.4.3).')->required(),
            'limit' => $schema->integer()->description('Maximum number of results to return (1–10). Defaults to 5.'),
        ];
    }
}
