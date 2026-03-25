<?php

namespace App\Mcp\Resources;

use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Property;
use App\Models\Scopes\TenantScope;
use App\Services\RagRetrievalService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('Legal risk profile for a property: industry classification, baseline and computed risk level, and top matching ADA lawsuit precedents based on open accessibility issues.')]
#[MimeType('application/json')]
class PropertyLegalRiskResource extends Resource implements HasUriTemplate
{
    public function __construct(
        private readonly Agency $agency,
        private readonly RagRetrievalService $ragService,
    ) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('property://{slug}/legal-risk');
    }

    public function handle(Request $request): Response
    {
        $slug = (string) $request->get('slug', '');

        $property = Property::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $this->agency->id)
            ->where('slug', $slug)
            ->first();

        if ($property === null) {
            return Response::error('Property not found for slug: '.$slug);
        }

        $industry = $property->industry;
        $industryValue = $industry?->value;
        $baselineRisk = $industry?->legalRiskLevel() ?? 'low';

        $openIssueCount = $property->issues()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('status', [IssueStatus::Open->value, IssueStatus::InProgress->value])
            ->count();

        if ($openIssueCount === 0) {
            return Response::json([
                'property' => [
                    'id' => $property->id,
                    'name' => $property->name,
                    'slug' => $property->slug,
                    'industry' => $industryValue,
                ],
                'baseline_risk' => $baselineRisk,
                'risk_level' => $baselineRisk,
                'open_issue_count' => 0,
                'top_lawsuits' => [],
            ]);
        }

        $topIssues = $property->issues()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('status', [IssueStatus::Open->value, IssueStatus::InProgress->value])
            ->orderByDesc('risk_weight')
            ->limit(5)
            ->get(['rule_key', 'wcag_criteria', 'description']);

        $queryParts = $topIssues
            ->map(fn ($issue) => trim("{$issue->rule_key} {$issue->wcag_criteria} {$issue->description}"))
            ->implode(' ');

        $industries = $industryValue ? [$industryValue] : null;

        $topLawsuits = $this->ragService->findLawsuits($queryParts, 5, $industries);

        $plaintiffWins = count(array_filter($topLawsuits, fn ($l) => ($l['outcome'] ?? '') === 'plaintiff_won'));

        return Response::json([
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
                'industry' => $industryValue,
            ],
            'baseline_risk' => $baselineRisk,
            'risk_level' => $this->resolveRiskLevel($baselineRisk, $plaintiffWins),
            'open_issue_count' => $openIssueCount,
            'top_lawsuits' => $topLawsuits,
        ]);
    }

    /**
     * Derive the final risk level from the industry baseline and matched lawsuit outcomes.
     *
     * @param  'high'|'medium'|'low'  $baseline
     * @return 'high'|'medium'|'low'
     */
    private function resolveRiskLevel(string $baseline, int $plaintiffWins): string
    {
        if ($baseline === 'high' || $plaintiffWins >= 2) {
            return 'high';
        }

        if ($baseline === 'medium' || $plaintiffWins >= 1) {
            return 'medium';
        }

        return 'low';
    }
}
