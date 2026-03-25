<?php

namespace App\Mcp\Tools;

use App\Services\RagRetrievalService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Return the top ADA lawsuit precedents most relevant to a specific accessibility violation rule and WCAG criterion. Optionally filter by industry to surface industry-specific legal risk.')]
class GetRelatedLawsuitsTool extends Tool
{
    public function __construct(private readonly RagRetrievalService $ragService) {}

    public function handle(Request $request): Response
    {
        $request->validate([
            'rule_key' => ['required', 'string'],
            'wcag_criteria' => ['required', 'string'],
            'industry' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $query = $request->get('rule_key').' '.$request->get('wcag_criteria');
        $industries = filled($request->get('industry')) ? [$request->get('industry')] : null;
        $limit = (int) ($request->get('limit') ?? 5);

        $lawsuits = $this->ragService->findLawsuits($query, $limit, $industries);

        return Response::json([
            'rule_key' => $request->get('rule_key'),
            'wcag_criteria' => $request->get('wcag_criteria'),
            'industry_filter' => $industries[0] ?? null,
            'total' => count($lawsuits),
            'lawsuits' => $lawsuits,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'rule_key' => $schema->string()->description('The axe-core rule key (e.g. image-alt, color-contrast).')->required(),
            'wcag_criteria' => $schema->string()->description('The WCAG criterion number (e.g. 1.1.1, 1.4.3).')->required(),
            'industry' => $schema->string()->description('Optional industry filter (e.g. retail, healthcare, finance). Omit to search all industries.'),
            'limit' => $schema->integer()->description('Maximum number of results to return (1–10). Defaults to 5.'),
        ];
    }
}
