<?php

namespace App\Mcp\Resources;

use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Property;
use App\Models\Scopes\TenantScope;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('Current open accessibility issues for a property, formatted as a readable document.')]
#[MimeType('text/plain')]
class PropertyIssuesResource extends Resource implements HasUriTemplate
{
    public function __construct(private readonly Agency $agency) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('property://{slug}/issues');
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

        $issues = $property->issues()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('status', [IssueStatus::Open->value, IssueStatus::InProgress->value])
            ->orderByDesc('risk_weight')
            ->get(['id', 'rule_key', 'severity', 'wcag_criteria', 'description', 'occurrence_count', 'risk_weight', 'page_url']);

        if ($issues->isEmpty()) {
            return Response::text("# {$property->name} — Open Issues\n\nNo open issues found.");
        }

        $lines = ["# {$property->name} — Open Issues", ''];

        foreach ($issues as $issue) {
            $severity = $issue->severity instanceof \App\Enums\IssueSeverity
                ? $issue->severity->value
                : (string) $issue->severity;

            $lines[] = "## Issue #{$issue->id}: {$issue->rule_key}";
            $lines[] = "- **Severity**: {$severity}";
            $lines[] = '- **WCAG**: '.($issue->wcag_criteria ?? 'N/A');
            $lines[] = '- **Occurrences**: '.$issue->occurrence_count;
            $lines[] = '- **Risk weight**: '.$issue->risk_weight;
            $lines[] = '- **Page**: '.$issue->page_url;
            if ($issue->description) {
                $lines[] = '- **Description**: '.$issue->description;
            }
            $lines[] = '';
        }

        return Response::text(implode("\n", $lines));
    }
}
